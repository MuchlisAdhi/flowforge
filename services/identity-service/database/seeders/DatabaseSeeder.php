<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'flowforge-demo'],
            [
                'name' => 'FlowForge Demo',
                'is_active' => true,
                'settings' => [
                    'max_workflows' => 100,
                    'max_runs_per_day' => 1000,
                ],
            ]
        );

        // Create default users
        User::firstOrCreate(
            ['email' => 'admin@flowforge.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin User',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'editor@flowforge.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Editor User',
                'password' => 'password',
                'role' => 'editor',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'viewer@flowforge.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Viewer User',
                'password' => 'password',
                'role' => 'viewer',
                'is_active' => true,
            ]
        );
    }
}
