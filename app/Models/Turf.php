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
}
