<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Saves and reads photos inside the database (the `images` table), so they are not lost when the
 * server restarts. Big phone photos are shrunk first so the database and the backups stay small.
 */
class ImageStore
{
    /** Saves an uploaded photo and returns the new images.id */
    public static function fromUpload(UploadedFile $file, int $maxSide = 1200): int
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime  = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        [$bytes, $mime] = self::shrink($bytes, $mime, $maxSide);

        return self::insertRow([
            'file_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            'image_data' => $bytes,
        ]);
    }

    /** Saves raw bytes (used when old photo files are moved into the database). */
    public static function fromBytes(string $bytes, string $mime, ?string $name = null): int
    {
        return self::insertRow([
            'file_name' => $name !== null ? mb_substr($name, 0, 255) : null,
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            'image_data' => $bytes,
        ]);
    }

    /**
     * Inserts one row of the images table. The photo is sent as binary (LOB) so it is stored
     * exactly as it is, on MySQL, PostgreSQL and SQLite. Returns the id of the row.
     */
    public static function insertRow(array $row): int
    {
        $now = now()->format('Y-m-d H:i:s');
        $row['created_at'] = $row['created_at'] ?? $now;
        $row['updated_at'] = $row['updated_at'] ?? $now;

        $columns = array_keys($row);
        $sql     = 'INSERT INTO images (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $pdo  = DB::connection()->getPdo();
        $stmt = $pdo->prepare($sql);

        $stream = null;
        foreach (array_values($row) as $i => $value) {
            if ($columns[$i] === 'image_data') {
                $stream = fopen('php://memory', 'r+');
                fwrite($stream, (string) $value);
                rewind($stream);
                $stmt->bindParam($i + 1, $stream, PDO::PARAM_LOB);
            } else {
                $stmt->bindValue($i + 1, $value);
            }
        }
        $stmt->execute();

        if (isset($row['id'])) {
            return (int) $row['id'];
        }

        return (int) $pdo->lastInsertId(DB::getDriverName() === 'pgsql' ? 'images_id_seq' : null);
    }

    /** The photo itself, as a string (PostgreSQL returns it as a stream, so that is handled here). */
    public static function data(int $id): ?string
    {
        $value = DB::table('images')->where('id', $id)->value('image_data');

        if ($value === null) {
            return null;
        }

        return is_resource($value) ? (string) stream_get_contents($value) : (string) $value;
    }

    /**
     * Makes a big photo smaller (longest side = $maxSide) and turns phone photos the right way up.
     * If the server cannot do it, or the photo is already small, the original bytes are kept.
     *
     * @return array{0:string,1:string} [bytes, mime]
     */
    private static function shrink(string $bytes, string $mime, int $maxSide): array
    {
        if (! extension_loaded('gd') || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [$bytes, $mime];
        }

        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return [$bytes, $mime];
        }

        $changed = false;

        // Phone photos are stored sideways with a note saying how to turn them; apply it.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif  = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
            $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($angle !== 0) {
                $rotated = imagerotate($img, $angle, 0);
                if ($rotated) {
                    $img     = $rotated;
                    $changed = true;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > $maxSide) {
            $scale = $maxSide / max($w, $h);
            $nw    = max(1, (int) round($w * $scale));
            $nh    = max(1, (int) round($h * $scale));
            $out   = imagecreatetruecolor($nw, $nh);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $img     = $out;
            $changed = true;
        }

        if (! $changed) {
            return [$bytes, $mime];
        }

        ob_start();
        match ($mime) {
            'image/png'  => imagepng($img, null, 6),
            'image/webp' => imagewebp($img, null, 82),
            default      => imagejpeg($img, null, 82),
        };
        $encoded = (string) ob_get_clean();

        return $encoded !== '' ? [$encoded, $mime] : [$bytes, $mime];
    }
}
