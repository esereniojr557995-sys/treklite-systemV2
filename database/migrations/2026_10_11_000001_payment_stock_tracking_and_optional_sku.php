<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe to run on a new database or on one that already has some of these changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Payment methods are Cash / GCash / Bank Transfer. A plain string keeps old PayMaya/Card sales valid.
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cash')->change();
        });
        if (! Schema::hasColumn('sales', 'payment_proof_hash')) {
            Schema::table('sales', function (Blueprint $table) {
                // A fingerprint of the payment photo, so the same screenshot cannot be used for two sales.
                $table->string('payment_proof_hash', 64)->nullable()->index();
            });
        }

        // Stock history: allow the "sale" movement type and remember what the system expected before the change.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });
        if (! Schema::hasColumn('stock_movements', 'quantity_before')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->integer('quantity_before')->nullable()->after('type');
            });
        }
        DB::statement('UPDATE stock_movements SET quantity_before = quantity_after - quantity_change WHERE quantity_before IS NULL');
        DB::statement("UPDATE stock_movements SET type = 'count' WHERE type = 'adjust'");

        // The item code (SKU) is made by the system, so it may be empty on old data.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('sku')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'quantity_before')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropColumn('quantity_before');
            });
        }
        if (Schema::hasColumn('sales', 'payment_proof_hash')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex(['payment_proof_hash']);
                $table->dropColumn('payment_proof_hash');
            });
        }
    }
};
