<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
    
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
    
    public function hasPermission(string $permission): bool
    {
        if ($this->name === 'admin') {
            return true;
        }
        
        // Check if permissions are loaded
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('name', $permission);
        }
        
        // If permissions aren't loaded, check via query
        return $this->permissions()->where('name', $permission)->exists();
    }

    public static function admin()
    {
        $role = static::where('name', 'admin')->first();
        
        if (!$role) {
            $role = static::create([
                'name' => 'admin',
                'description' => 'Administrator with full access'
            ]);
            
            // Attach all permissions to admin role
            $permissions = Permission::pluck('id');
            $role->permissions()->sync($permissions);
        }
        
        return $role;
    }

    public static function customer()
    {
        return static::firstOrCreate(
            ['name' => 'customer'],
            [
                'name' => 'customer',
                'description' => 'Regular customer'
            ]
        );
    }
}
