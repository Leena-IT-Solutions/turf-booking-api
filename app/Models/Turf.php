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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
            ->withPivot('is_active')
            ->withTimestamps();
    }
}
