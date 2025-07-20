<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'description' => 'Can view dashboard'],
            
            // Users
            ['name' => 'view_users', 'description' => 'Can view users'],
            ['name' => 'create_users', 'description' => 'Can create users'],
            ['name' => 'edit_users', 'description' => 'Can edit users'],
            ['name' => 'delete_users', 'description' => 'Can delete users'],
            
            // Roles
            ['name' => 'view_roles', 'description' => 'Can view roles'],
            ['name' => 'create_roles', 'description' => 'Can create roles'],
            ['name' => 'edit_roles', 'description' => 'Can edit roles'],
            ['name' => 'delete_roles', 'description' => 'Can delete roles'],
            ['name' => 'assign_roles', 'description' => 'Can assign roles to users'],
            
            // Products
            ['name' => 'view_products', 'description' => 'Can view products'],
            ['name' => 'create_products', 'description' => 'Can create products'],
            ['name' => 'edit_products', 'description' => 'Can edit products'],
            ['name' => 'delete_products', 'description' => 'Can delete products'],
            
            // Categories
            ['name' => 'view_categories', 'description' => 'Can view categories'],
            ['name' => 'create_categories', 'description' => 'Can create categories'],
            ['name' => 'edit_categories', 'description' => 'Can edit categories'],
            ['name' => 'delete_categories', 'description' => 'Can delete categories'],
            
            // Orders
            ['name' => 'view_orders', 'description' => 'Can view orders'],
            ['name' => 'create_orders', 'description' => 'Can create orders'],
            ['name' => 'edit_orders', 'description' => 'Can edit orders'],
            ['name' => 'delete_orders', 'description' => 'Can delete orders'],
            
            // Settings
            ['name' => 'manage_settings', 'description' => 'Can manage application settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
