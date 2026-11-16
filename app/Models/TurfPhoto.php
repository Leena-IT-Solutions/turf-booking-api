<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TurfPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'photo',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TurfPhoto $photo) {
            if (!empty($photo->photo) && Storage::disk('public')->exists($photo->photo)) {
                Storage::disk('public')->delete($photo->photo);
            }
        });
    }

    public function turf(): BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }
}
