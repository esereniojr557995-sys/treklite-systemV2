<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Clean start: only the two sign-in accounts. Safe to run again (existing accounts are left as they are). */
    public function run(): void
    {
        // CHANGE THESE PASSWORDS before real use (My Profile > Change password).
        User::firstOrCreate(
            ['email' => 'owner@treklite.test'],
            ['name' => 'Owner', 'role' => 'owner', 'password' => Hash::make('password')]
        );
        User::firstOrCreate(
            ['email' => 'staff@treklite.test'],
            ['name' => 'Staff', 'role' => 'staff', 'password' => Hash::make('password')]
        );
    }
}
