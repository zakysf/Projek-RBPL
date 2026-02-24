<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    public function therapist()
    {
        return $this->belongsTo(\App\Models\Therapist::class);
    }
    protected $fillable = [
        'therapist_id',
        'item_name',
        'quantity',
        'notes',
        'status',
    ];
}
