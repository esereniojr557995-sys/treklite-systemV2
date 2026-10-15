<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Sales can be VOIDED (reversed) by the Owner: who, when and why are kept, stock is put back.
 * - Products can be ARCHIVED instead of deleted (history stays, they just stop appearing in sales / stock lists).
 * - Activity log: a trail of important actions (void, product changes, accounts, backups).
 * Safe to run on a database that already has some of these.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'voided_at')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->timestamp('voided_at')->nullable()->index();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('void_reason', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('products', 'is_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40)->index();
                $table->string('description', 500);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        if (Schema::hasColumn('products', 'is_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('sales', 'voided_at')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('voided_by');
                $table->dropColumn(['voided_at', 'void_reason']);
            });
        }
    }
};
