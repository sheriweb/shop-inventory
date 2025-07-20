<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role_id',
        'is_customer',
        'permissions'
    ];

    protected $appends = ['all_permissions', 'can'];

    /**
     * Get the user's roles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole($role): bool
    {
        if (!$this->exists) {
            return false;
        }

        try {
            // Load roles if not already loaded
            if (!$this->relationLoaded('roles')) {
                $this->load('roles');
            }

            if (is_string($role)) {
                return $this->roles->contains('name', $role);
            }

            if ($role instanceof Role) {
                return $this->roles->contains('id', $role->id);
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Error checking user role: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permissions for the user.
     */
    public function getAllPermissionsAttribute()
    {
        $permissions = $this->permissions ?? [];

        // Get permissions from all roles
        foreach ($this->roles as $role) {
            if ($role->permissions) {
                $permissions = array_merge($permissions, $role->permissions);
            }
        }

        return array_unique($permissions);
    }

    /**
     * Check if the user has a specific permission.
     */
    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user has a specific permission.
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission($permission): bool
    {
        // Check if user has admin role
        if ($this->isAdmin()) {
            return true;
        }

        // Check direct permissions
        if ($this->relationLoaded('permissions') && $this->permissions->contains('name', $permission)) {
            return true;
        }

        // Check if we need to load the permissions relationship
        if (!$this->relationLoaded('permissions')) {
            $hasPermission = $this->permissions()->where('name', $permission)->exists();
            if ($hasPermission) {
                return true;
            }
        }

        // Check role permissions
        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->hasPermission($permission)) {
                    return true;
                }
            }
        } else {
            // If roles aren't loaded, check via query
            $hasPermission = $this->roles()->whereHas('permissions', function($q) use ($permission) {
                $q->where('name', $permission);
            })->exists();
            
            if ($hasPermission) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the can attribute for frontend use.
     */
    public function getCanAttribute()
    {
        $permissions = [];

        foreach ($this->all_permissions as $permission) {
            $permissions[$permission] = true;
        }

        return $permissions;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_customer' => 'boolean',
        'permissions' => 'array',
        'role_id' => 'integer',
    ];



    /**
     * Get the sales made by the user.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }



    /**
     * Check if the user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return (bool) $this->is_customer;
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function can($permission, $arguments = []): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->hasPermission($permission);
    }

    /**
     * Determine if the user has any of the given abilities.
     *
     * @param  array|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function canAny($abilities, $arguments = [])
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissions = is_array($abilities) ? $abilities : [$abilities];

        foreach ($permissions as $permission) {
            if ($this->can($permission, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope a query to only include admins.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    /**
     * Scope a query to only include admin users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', function($q) {
            $q->where('name', 'admin');
        });
    }

    /**
     * Scope a query to only include customers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCustomers($query)
    {
        return $query->where('is_customer', true);
    }
}
