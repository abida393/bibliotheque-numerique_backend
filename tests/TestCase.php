<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUpRoles(): void
    {
        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'moderator']);
        Role::firstOrCreate(['name' => 'admin']);
    }
}
