<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'treatment_id',
        'therapist_id',
        'reservation_date',
        'reservation_time',
        'duration',
        'status',
        'payment_status',
        'paid_at',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }
}