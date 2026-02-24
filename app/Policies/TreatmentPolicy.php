<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Treatment;

class TreatmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function view(User $user, Treatment $treatment): bool
    {
        return $user->role === 'manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function update(User $user, Treatment $treatment): bool
    {
        return $user->role === 'manager';
    }

    public function delete(User $user, Treatment $treatment): bool
    {
        return $user->role === 'manager';
    }
}