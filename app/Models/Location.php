<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'contact_number',
        'email',
    ];

    /**
     * Get the attributes cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Get the user that owns the location.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the turfs for this location.
     */
    public function turfs()
    {
        return $this->hasMany(Turf::class);
    }
}
