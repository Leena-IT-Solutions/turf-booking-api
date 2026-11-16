<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurfQrScan extends Model
{
    protected $fillable = [
        'turf_id',
        'platform',
        'source',
        'user_agent',
        'ip_hash',
        'converted_user_id',
        'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    /**
     * Detect platform from a User-Agent string.
     */
    public static function detectPlatform(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if (str_contains($ua, 'android')) {
            return 'android';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            return 'ios';
        }
        return 'desktop';
    }
}
