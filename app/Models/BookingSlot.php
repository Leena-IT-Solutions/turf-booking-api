<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_date_id',
        'slot_id',
    ];

    public function bookingDate()
    {
        return $this->belongsTo(BookingDate::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
