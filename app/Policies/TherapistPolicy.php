<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Therapist;

class TherapistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function view(User $user, Therapist $therapist): bool
    {
        return $user->role === 'manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function update(User $user, Therapist $therapist): bool
    {
        return $user->role === 'manager';
    }

    public function delete(User $user, Therapist $therapist): bool
    {
        return $user->role === 'manager';
    }
}