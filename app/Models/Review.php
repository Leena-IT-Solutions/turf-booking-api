<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'turf_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the user that wrote the review.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the turf that was reviewed.
     */
    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
