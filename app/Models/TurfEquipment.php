<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurfEquipment extends Model
{
    use HasFactory;

    protected $table = 'turf_equipments';

    protected $fillable = [
        'turf_id',
        'equipment_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function turf(): BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
