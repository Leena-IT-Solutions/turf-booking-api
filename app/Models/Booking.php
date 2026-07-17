<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'turf_id',
        'slot_id',
        'booking_date',
        'booking_type',
        'status',
        'payment_status',
        'price',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
