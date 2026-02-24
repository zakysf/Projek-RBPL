<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Therapist extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'specialization',
        'is_active',
    ];

    public function treatments()
    {
        return $this->belongsToMany(Treatment::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function stockRequests()
    {
        return $this->hasMany(\App\Models\StockRequest::class);
    }
}