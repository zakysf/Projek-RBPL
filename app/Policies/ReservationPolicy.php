<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Reservation;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['manager', 'cashier']);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return in_array($user->role, ['manager', 'cashier']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['manager', 'cashier']);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->role === 'manager';
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->role === 'manager';
    }
}