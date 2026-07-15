<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurfSport extends Model
{
    use HasFactory;

    protected $table = 'turf_sports';

    protected $fillable = [
        'turf_id',
        'sport',
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
