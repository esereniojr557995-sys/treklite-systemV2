<?php

use App\Support\ImageStore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * sales.payment_proof_path (file)             ->  sales.payment_proof_id (photo in the `images` table)
 * sales.voided_at / voided_by / void_reason   ->  their own `voids` table (one row per voided sale)
 *
 * Existing data is moved over. Safe to run again on a database that is already converted.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------- voids ----------------
        if (! Schema::hasTable('voids')) {
            Schema::create('voids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
                $table->foreignId('voided_by')->constrained('users');
                $table->string('void_reason', 255);
                $table->timestamp('voided_at');
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('sales', 'voided_at')) {
            $ownerId = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id');

            foreach (DB::table('sales')->whereNotNull('voided_at')->orderBy('id')->get() as $s) {
                if (DB::table('voids')->where('sale_id', $s->id)->exists()) {
                    continue;
                }
                DB::table('voids')->insert([
                    'sale_id'     => $s->id,
                    // the account that voided it may have been removed; fall back to an Owner so the record is kept
                    'voided_by'   => $s->voided_by ?? $ownerId ?? $s->user_id,
                    'void_reason' => $s->void_reason ?: 'No reason recorded',
                    'voided_at'   => $s->voided_at,
                    'created_at'  => $s->voided_at,
                    'updated_at'  => $s->voided_at,
                ]);
            }
        }

        // ---------------- payment photos ----------------
        if (! Schema::hasColumn('sales', 'payment_proof_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('payment_proof_id')->nullable()->after('payment_reference')->constrained('images')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('sales', 'payment_proof_path')) {
            foreach (DB::table('sales')->whereNotNull('payment_proof_path')->orderBy('id')->get(['id', 'payment_proof_path']) as $s) {
                try {
                    $disk = Storage::disk('public');
                    if (! $disk->exists($s->payment_proof_path)) {
                        Log::warning("Sale {$s->id}: payment photo file {$s->payment_proof_path} was not found on the server.");
                        continue;
                    }
                    $bytes = $disk->get($s->payment_proof_path);
                    $mime  = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
                    if (str_starts_with($mime, 'image/')) {
                        $imageId = ImageStore::fromBytes($bytes, $mime, basename($s->payment_proof_path));
                        DB::table('sales')->where('id', $s->id)->update(['payment_proof_id' => $imageId]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Sale {$s->id}: payment photo could not be moved into the database: " . $e->getMessage());
                }
            }
        }

        // ---------------- remove the old columns ----------------
        if (Schema::hasColumn('sales', 'voided_by')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('voided_by');
            });
        }
        if (Schema::hasColumn('sales', 'voided_at')) {
            if (Schema::hasIndex('sales', 'sales_voided_at_index')) {
                Schema::table('sales', function (Blueprint $table) {
                    $table->dropIndex('sales_voided_at_index');
                });
            }
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['voided_at', 'void_reason']);
            });
        }
        if (Schema::hasColumn('sales', 'payment_proof_path')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('payment_proof_path');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales', 'voided_at')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->timestamp('voided_at')->nullable()->index();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('void_reason', 255)->nullable();
            });

            if (Schema::hasTable('voids')) {
                foreach (DB::table('voids')->get() as $v) {
                    DB::table('sales')->where('id', $v->sale_id)->update([
                        'voided_at' => $v->voided_at, 'voided_by' => $v->voided_by, 'void_reason' => $v->void_reason,
                    ]);
                }
            }
        }

        if (! Schema::hasColumn('sales', 'payment_proof_path')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('payment_proof_path')->nullable()->after('payment_reference');
            });

            foreach (DB::table('sales')->whereNotNull('payment_proof_id')->get(['id', 'payment_proof_id']) as $s) {
                $img   = DB::table('images')->where('id', $s->payment_proof_id)->first(['mime_type']);
                $bytes = ImageStore::data($s->payment_proof_id);
                if ($img && $bytes !== null) {
                    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$img->mime_type] ?? 'img';
                    $path = "payment-proofs/restored-{$s->id}.{$ext}";
                    Storage::disk('public')->put($path, $bytes);
                    DB::table('sales')->where('id', $s->id)->update(['payment_proof_path' => $path]);
                }
            }
        }

        if (Schema::hasColumn('sales', 'payment_proof_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_proof_id');
            });
        }

        Schema::dropIfExists('voids');
    }
};
