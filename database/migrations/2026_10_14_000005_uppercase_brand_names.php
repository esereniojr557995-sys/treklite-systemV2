<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brand names are now always saved in CAPITAL LETTERS (see Brand::setNameAttribute).
 * This changes the brands that already exist so they match.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('brands')->get(['id', 'name']) as $b) {
            $upper = mb_strtoupper(trim($b->name));

            if ($upper !== $b->name) {
                DB::table('brands')->where('id', $b->id)->update(['name' => $upper]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the old capitalisation is not kept.
    }
};
