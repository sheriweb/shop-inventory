<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Define all available permissions
        $allPermissions = [
            'view_dashboard',
            'view_users',
            'manage_users',
            'view_roles',
            'manage_roles',
            'view_products',
            'manage_products',
            'view_categories',
            'manage_categories',
            'view_sales',
            'manage_sales',
            'view_reports',
        ];

        // Create admin role with all permissions
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'description' => 'Administrator with full access',
                'permissions' => ['*'] // Admin has all permissions
            ]
        );

        // Create customer role with limited permissions
        $customerRole = Role::updateOrCreate(
            ['name' => 'customer'],
            [
                'description' => 'Regular customer',
                'permissions' => [
                    'view_dashboard',
                    'view_products',
                    'view_categories',
                    'view_sales',
                ]
            ]
        );

        // Find or create admin user
        $adminUser = User::firstOrNew(['email' => 'admin@example.com']);
        $adminUser->fill([
            'name' => 'Admin',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'is_customer' => false,
            'email_verified_at' => now(),
        ]);
        $adminUser->save();
    }
}
