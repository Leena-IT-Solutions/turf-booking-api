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

    /**
     * Scope a query to only include manageable locations for the given or authenticated user.
     */
    public function scopeManageable($query, ?User $user = null)
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('turf-admin')) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereHas('turfs', function ($q) use ($user) {
            $q->whereIn('id', $user->assignedTurfs()->pluck('turfs.id'));
        });
    }
}
