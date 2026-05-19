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
        $tenant = Tenant::create([
            'name' => 'FlowForge Demo',
            'slug' => 'flowforge-demo',
            'is_active' => true,
            'settings' => [
                'max_workflows' => 100,
                'max_runs_per_day' => 1000,
            ],
        ]);

        // Create default users
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@flowforge.local',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor User',
            'email' => 'editor@flowforge.local',
            'password' => 'password',
            'role' => 'editor',
            'is_active' => true,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Viewer User',
            'email' => 'viewer@flowforge.local',
            'password' => 'password',
            'role' => 'viewer',
            'is_active' => true,
        ]);
    }
}
