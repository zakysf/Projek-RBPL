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
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    protected static function booted()
    {
        static::created(function ($reservation) {
            $reservation->payment()->create([
                'amount' => $reservation->treatment->price,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
            ]);
        });
    }
}