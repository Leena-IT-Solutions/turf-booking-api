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

    protected static function booted(): void
    {
        static::deleting(function (Location $location) {
            $turfs = $location->turfs()->with('photos')->get();
            foreach ($turfs as $turf) {
                foreach ($turf->photos as $photo) {
                    if (!empty($photo->photo) && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo->photo)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->photo);
                    }
                }
            }
            if (session('active_location_id') && (int) session('active_location_id') === (int) $location->id) {
                session()->forget(['active_location_id', 'active_turf_id']);
            }
        });
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

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('turfs', function ($sq) use ($user) {
                  $sq->whereIn('turfs.id', $user->assignedTurfs()->pluck('turfs.id'));
              });
        });
    }
}
