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
        return $this->hasMany(TurfFacility::class);
    }

    public function turfEquipments()
    {
        return $this->hasMany(TurfEquipment::class);
    }

    public function sports()
    {
        return $this->hasMany(TurfSport::class);
    }
}
