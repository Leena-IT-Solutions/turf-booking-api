<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurfFacility extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'facility',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function turf(): BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }
}
