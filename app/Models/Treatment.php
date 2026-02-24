<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $fillable = [
        'name',
        'description',
        'duration',
        'price',
        'is_active',
    ];

    public function therapists()
    {
        return $this->belongsToMany(Therapist::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}