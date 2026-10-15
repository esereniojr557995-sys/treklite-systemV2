<?php

use App\Support\ImageStore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * products:         category / brand (text)  ->  category_id / brand_id,   image_path (file)  ->  image_id (photo in the database)
 * product_variants: size / color (text)      ->  size_id / color_id,       new initial_stock
 *
 * Existing data is moved over, nothing is lost. Safe to run again on a database that is already converted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ---------------- products ----------------
        if (Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('image_id')->nullable()->after('name')->constrained('images')->nullOnDelete();
                $table->foreignId('brand_id')->nullable()->after('image_id')->constrained('brands')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->after('brand_id')->constrained('categories')->nullOnDelete();
            });

            $ids = ['brands' => [], 'categories' => []];
            $ensure = function (string $table, ?string $name) use (&$ids, $now): ?int {
                $name = trim((string) $name);
                if ($name === '') {
                    return null;
                }
                $key = mb_strtolower($name);
                if (! isset($ids[$table][$key])) {
                    $ids[$table][$key] = DB::table($table)->insertGetId([
                        'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                return $ids[$table][$key];
            };

            foreach (DB::table('products')->orderBy('id')->get(['id', 'brand', 'category', 'image_path']) as $p) {
                $update = [
                    'brand_id'    => $ensure('brands', $p->brand),
                    'category_id' => $ensure('categories', $p->category),
                ];

                // Move the old photo file into the database (if the file is still on the server).
                if ($p->image_path) {
                    try {
                        $disk = Storage::disk('public');
                        if ($disk->exists($p->image_path)) {
                            $bytes = $disk->get($p->image_path);
                            $mime  = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
                            if (str_starts_with($mime, 'image/')) {
                                $update['image_id'] = ImageStore::fromBytes($bytes, $mime, basename($p->image_path));
                            }
                        } else {
                            Log::warning("Product {$p->id}: photo file {$p->image_path} was not found, the placeholder will be shown.");
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Product {$p->id}: photo could not be moved into the database: " . $e->getMessage());
                    }
                }

                DB::table('products')->where('id', $p->id)->update($update);
            }

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['category', 'brand', 'image_path']);
            });
        }

        // ---------------- product_variants ----------------
        if (Schema::hasColumn('product_variants', 'size')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->foreignId('size_id')->nullable()->after('id')->constrained('sizes')->nullOnDelete();
                $table->foreignId('color_id')->nullable()->after('size_id')->constrained('colors')->nullOnDelete();
                $table->integer('initial_stock')->default(0)->after('price');
            });

            // Sizes are listed in natural order (30, 32, 34 ... / S, M, L is kept as typed).
            $sizeNames = DB::table('product_variants')->whereNotNull('size')->where('size', '!=', '')->distinct()->pluck('size')
                ->map(fn ($s) => trim($s))->filter()->unique(fn ($s) => mb_strtolower($s))->values()->all();
            natcasesort($sizeNames);

            $sizeIds = [];
            $order   = 0;
            foreach ($sizeNames as $name) {
                $sizeIds[mb_strtolower($name)] = DB::table('sizes')->insertGetId([
                    'name' => $name, 'sort_order' => $order++, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $colorIds = [];
            foreach (DB::table('product_variants')->whereNotNull('color')->where('color', '!=', '')->distinct()->pluck('color') as $c) {
                $c = trim($c);
                if ($c !== '' && ! isset($colorIds[mb_strtolower($c)])) {
                    $colorIds[mb_strtolower($c)] = DB::table('colors')->insertGetId([
                        'name' => $c, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            foreach (DB::table('product_variants')->get(['id', 'size', 'color']) as $v) {
                DB::table('product_variants')->where('id', $v->id)->update([
                    'size_id'  => $sizeIds[mb_strtolower(trim((string) $v->size))] ?? null,
                    'color_id' => $colorIds[mb_strtolower(trim((string) $v->color))] ?? null,
                ]);
            }

            // Items that were added with an opening-stock delivery keep that number as their initial stock.
            foreach (DB::table('stock_movements')->where('note', 'like', 'Opening stock%')->orderBy('id')->get(['variant_id', 'quantity_change']) as $m) {
                DB::table('product_variants')->where('id', $m->variant_id)->update(['initial_stock' => max(0, (int) $m->quantity_change)]);
            }

            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn(['size', 'color']);
            });
        }
    }

    public function down(): void
    {
        // Brings the text columns back and fills them from the lookup tables.
        // Photos are written back as files on the server.
        if (! Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('category')->nullable()->after('name');
                $table->string('brand')->nullable()->after('category');
                $table->string('image_path')->nullable()->after('description');
            });

            foreach (DB::table('products')->get(['id', 'brand_id', 'category_id', 'image_id']) as $p) {
                $update = [
                    'brand'    => $p->brand_id ? DB::table('brands')->where('id', $p->brand_id)->value('name') : null,
                    'category' => $p->category_id ? DB::table('categories')->where('id', $p->category_id)->value('name') : null,
                ];
                if ($p->image_id) {
                    $img   = DB::table('images')->where('id', $p->image_id)->first(['mime_type']);
                    $bytes = ImageStore::data($p->image_id);
                    if ($img && $bytes !== null) {
                        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$img->mime_type] ?? 'img';
                        $path = "products/restored-{$p->id}.{$ext}";
                        Storage::disk('public')->put($path, $bytes);
                        $update['image_path'] = $path;
                    }
                }
                DB::table('products')->where('id', $p->id)->update($update);
            }

            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('image_id');
                $table->dropConstrainedForeignId('brand_id');
                $table->dropConstrainedForeignId('category_id');
            });
        }

        if (! Schema::hasColumn('product_variants', 'size')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('size', 50)->nullable()->after('product_id');
                $table->string('color', 50)->nullable()->after('size');
            });

            foreach (DB::table('product_variants')->get(['id', 'size_id', 'color_id']) as $v) {
                DB::table('product_variants')->where('id', $v->id)->update([
                    'size'  => $v->size_id ? DB::table('sizes')->where('id', $v->size_id)->value('name') : null,
                    'color' => $v->color_id ? DB::table('colors')->where('id', $v->color_id)->value('name') : null,
                ]);
            }

            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('size_id');
                $table->dropConstrainedForeignId('color_id');
                $table->dropColumn('initial_stock');
            });
        }
    }
};
