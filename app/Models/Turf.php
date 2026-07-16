<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turf extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'type',
        'description',
        'area',
        'is_active',
        'equipments',
        'pricing_wizard_data',
        'is_online_payment_active',
        'is_part_payment_active',
        'is_pay_at_location_active',
        'part_payment_type',
        'part_payment_value',
        'is_booking_open',
        'booking_open_days',
        'is_manager_booking_active',
        'is_cancellation_active',
        'cancellation_hours',
        'cancellation_fee',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pricing_wizard_data' => 'array',
        'is_online_payment_active' => 'boolean',
        'is_part_payment_active' => 'boolean',
        'is_pay_at_location_active' => 'boolean',
        'part_payment_type' => 'string',
        'part_payment_value' => 'decimal:2',
        'is_booking_open' => 'boolean',
        'booking_open_days' => 'integer',
        'is_manager_booking_active' => 'boolean',
        'is_cancellation_active' => 'boolean',
        'cancellation_hours' => 'integer',
        'cancellation_fee' => 'decimal:2',
    ];

    /**
     * Get the location that owns the turf.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function photos()
    {
        return $this->hasMany(TurfPhoto::class);
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class);
    }

    public function turfEquipments()
    {
        return $this->belongsToMany(Equipment::class, 'equipment_turf', 'turf_id', 'equipment_id');
    }

    public function sports()
    {
        return $this->belongsToMany(Sport::class);
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class, 'slot_turf')
            ->withPivot('is_active', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include manageable turfs for the given or authenticated user.
     */
    public function scopeManageable($query, ?User $user = null)
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('turf-admin')) {
            return $query->whereHas('location', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->whereIn('id', $user->assignedTurfs()->pluck('turfs.id'));
    }
}
