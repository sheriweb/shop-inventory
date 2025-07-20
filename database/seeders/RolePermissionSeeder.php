<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin role
        $adminRole = Role::admin();
        
        // Create customer role with no default permissions
        $customerRole = Role::firstOrCreate(
            ['name' => 'customer'],
            [
                'name' => 'customer',
                'description' => 'Regular customer with no default permissions'
            ]
        );
        
        // Create admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_customer' => false,
            ]
        );
        
        // Assign admin role to admin user
        if (!$admin->roles()->where('roles.id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id);
        }
        
        // Create a sample customer user
        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_customer' => true,
            ]
        );
        
        // Assign customer role to customer user
        if (!$customer->roles()->where('roles.id', $customerRole->id)->exists()) {
            $customer->roles()->attach($customerRole->id);
        }
    }
}
