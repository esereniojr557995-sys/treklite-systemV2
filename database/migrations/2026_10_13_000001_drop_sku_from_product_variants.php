<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SKU (item code) is no longer used anywhere in the system, so the column is removed.
 * Nothing else depends on it. Safe to run on a database that has already had it removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'sku')) {
            return;
        }

        // The column carried a UNIQUE index; drop that first (guarded so it cannot fail if the index is already gone).
        if (Schema::hasIndex('product_variants', 'product_variants_sku_unique')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropUnique('product_variants_sku_unique');
            });
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_variants', 'sku')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('sku')->nullable()->unique()->after('product_id');
            });
        }
    }
};
