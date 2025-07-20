<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Role $role): bool
    {
        // Prevent modifying admin and customer roles
        if (in_array($role->name, ['admin', 'customer'])) {
            return false;
        }
        return $user->isAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        // Prevent deleting admin and customer roles
        if (in_array($role->name, ['admin', 'customer'])) {
            return false;
        }
        return $user->isAdmin() && $role->users()->count() === 0;
    }
}
