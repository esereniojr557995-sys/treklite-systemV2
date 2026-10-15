<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs.action_type groups the entries (sale, product, user, backup, other),
 * so the Activity Log can show a different color for each kind of action.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('activity_logs', 'action_type')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('action_type', 20)->default('other')->after('user_id')->index();
        });

        foreach (['sale_' => 'sale', 'product_' => 'product', 'staff_' => 'user', 'backup_' => 'backup'] as $prefix => $type) {
            DB::table('activity_logs')->where('action', 'like', $prefix . '%')->update(['action_type' => $type]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'action_type')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropIndex(['action_type']);
                $table->dropColumn('action_type');
            });
        }
    }
};
