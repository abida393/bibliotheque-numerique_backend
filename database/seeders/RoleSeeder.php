<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate makes the seeder idempotent — safe to re-run without migrate:fresh
        Role::firstOrCreate(['name' => 'user',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',     'guard_name' => 'web']);
    }
}
