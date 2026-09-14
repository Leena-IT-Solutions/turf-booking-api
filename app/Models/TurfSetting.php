<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'company_name',
        'company_email',
        'company_phone',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'gst_number',
    ];

    public function turf(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }
}
