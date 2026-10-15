<?php

namespace App\Services;

use App\Support\ImageStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Database backup and restore without needing mysqldump on the server.
 * A backup is one JSON file holding every row of the business tables.
 * Product and payment photos live in the `images` table, so they are inside the file too
 * (as text, because a JSON file cannot hold raw picture bytes).
 */
class BackupService
{
    /** Parents first, then the tables that point to them. */
    public const TABLES = [
        'users', 'categories', 'brands', 'sizes', 'colors', 'images', 'products', 'product_variants',
        'sales', 'voids', 'sale_items', 'stock_movements', 'activity_logs',
    ];

    /** Backups made before the database update (version 1) have a different structure and cannot be restored. */
    private const VERSION = 2;

    /** Photo bytes are saved as base64 text inside the JSON file. */
    private const BINARY = ['images' => 'image_data'];

    /** Photos are inserted a few at a time, so one insert never gets bigger than the database allows. */
    private const CHUNK = ['images' => 5];

    private const DIR = 'backups';

    /** Writes a backup file on the server and returns its file name. */
    public function create(string $suffix = ''): string
    {
        $payload = [
            'format'     => 'treklite-backup',
            'version'    => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'tables'     => [],
        ];

        foreach (self::TABLES as $table) {
            $payload['tables'][$table] = DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table) {
                $row = (array) $row;
                if (isset(self::BINARY[$table])) {
                    $col       = self::BINARY[$table];
                    $row[$col] = base64_encode(is_resource($row[$col]) ? (string) stream_get_contents($row[$col]) : (string) $row[$col]);
                }

                return $row;
            })->all();
        }

        $name = 'treklite-backup-' . now()->format('Ymd-His') . $suffix . '.json';
        Storage::disk('local')->put(self::DIR . '/' . $name, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $name;
    }

    /** Backups stored on the server, newest first. */
    public function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => $this->isValidName(basename($f)))
            ->map(fn ($f) => [
                'name'     => basename($f),
                'size'     => $disk->size($f),
                'modified' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($f)),
            ])
            ->sortByDesc('modified')
            ->values()
            ->all();
    }

    /** Full path of a stored backup, or null if the name is not a real backup. */
    public function path(string $name): ?string
    {
        if (! $this->isValidName($name) || ! Storage::disk('local')->exists(self::DIR . '/' . $name)) {
            return null;
        }

        return Storage::disk('local')->path(self::DIR . '/' . $name);
    }

    /**
     * Replaces ALL business data with the contents of a backup file.
     * Everything happens in one transaction: if anything is wrong, nothing changes.
     *
     * @return array<string,int> number of rows restored per table
     */
    public function restore(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['format'] ?? null) !== 'treklite-backup' || ! is_array($data['tables'] ?? null)) {
            throw new InvalidArgumentException('This is not a valid Treklite backup file.');
        }

        if (($data['version'] ?? 1) < self::VERSION) {
            throw new InvalidArgumentException('This backup was made before the database update (photos, brands, categories, sizes, colors), so it cannot be restored. Create a new backup.');
        }

        // Validate everything BEFORE touching the database.
        foreach (self::TABLES as $table) {
            if (! isset($data['tables'][$table]) || ! is_array($data['tables'][$table])) {
                throw new InvalidArgumentException("The backup file is missing the \"{$table}\" table.");
            }
            $allowed = Schema::getColumnListing($table);
            foreach ($data['tables'][$table] as $row) {
                if (! is_array($row) || array_diff(array_keys($row), $allowed)) {
                    throw new InvalidArgumentException("The backup file does not match the current database structure (table \"{$table}\").");
                }
            }
        }

        $counts = [];

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach (self::TABLES as $table) {
                DB::table($table)->delete();
            }
            foreach (self::TABLES as $table) {
                foreach (array_chunk($data['tables'][$table], self::CHUNK[$table] ?? 200) as $chunk) {
                    if (isset(self::BINARY[$table])) {
                        // Photos go in one row at a time as binary, exactly as they were.
                        foreach ($chunk as $row) {
                            $row[self::BINARY[$table]] = base64_decode((string) $row[self::BINARY[$table]], true);
                            ImageStore::insertRow($row);
                        }
                    } else {
                        DB::table($table)->insert($chunk);
                    }
                }
                $counts[$table] = count($data['tables'][$table]);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }

        return $counts;
    }

    /** Deletes automatic (nightly) backups older than the given number of days. Manual backups are never deleted. */
    public function pruneAutomatic(int $keepDays = 30): int
    {
        $disk    = Storage::disk('local');
        $limit   = now()->subDays($keepDays)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(self::DIR) as $file) {
            if (str_ends_with($file, '-auto.json') && $this->isValidName(basename($file)) && $disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^treklite-backup-\d{8}-\d{6}(-before-restore|-auto)?\.json$/', $name);
    }
}
