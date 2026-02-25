<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id',
        'amount',
        'payment_method',
        'payment_status',
        'paid_at'
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    protected static function booted()
    {
        static::updated(function ($payment) {
            if ($payment->payment_status === 'paid') {
                $payment->reservation->update([
                    'status' => 'completed',
                ]);
            }
        });
    }
}