<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Models\Contracts\FilamentUser;


class User extends Authenticatable implements FilamentUser
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

    /**
     * @var string[]
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_customer' => 'boolean',
        'permissions' => 'array',
        'role_id' => 'integer',
    ];

    /**
     * @var string[]
     */
    protected $appends = ['all_permissions', 'can'];

    /**
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @param $role
     * @return bool
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
     * @return array|mixed
     */
    public function getAllPermissionsAttribute(): mixed
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
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @param $permission
     * @return bool
     */
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
     * @return array
     */
    public function getCanAttribute(): array
    {
        $permissions = [];

        foreach ($this->all_permissions as $permission) {
            $permissions[$permission] = true;
        }

        return $permissions;
    }

    /**
     * @return HasMany
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    /**
     * @return bool
     */
    public function isCustomer(): bool
    {
        return (bool) $this->is_customer;
    }

    /**
     * @param $permission
     * @param $arguments
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
     * @param $abilities
     * @param $arguments
     * @return bool
     */
    public function canAny($abilities, $arguments = []): bool
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
     * @param $query
     * @return mixed
     */
    public function scopeAdmins($query): mixed
    {
        return $query->whereHas('roles', function($q) {
            $q->where('name', 'admin');
        });
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeCustomers($query): mixed
    {
        return $query->where('is_customer', true);
    }
    
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
