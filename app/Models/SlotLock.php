<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'slot_id',
        'lock_date',
        'reason',
        'created_by_user_id',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
