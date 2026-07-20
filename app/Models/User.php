<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'email', 'mobile', 'password', 'is_quick_created'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_quick_created' => 'boolean',
        ];
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Retract/remove a role from the user.
     */
    public function retractRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);
    }

    /**
     * Get the staff members assigned by this user (if Turf Admin).
     */
    public function staffMembers()
    {
        return $this->hasMany(StaffMember::class, 'turf_admin_id');
    }

    /**
     * Get the staff roles assigned to this user.
     */
    public function staffAssignments()
    {
        return $this->hasMany(StaffMember::class, 'user_id');
    }

    /**
     * Get the turfs explicitly assigned to this staff member.
     */
    public function assignedTurfs()
    {
        return $this->belongsToMany(Turf::class, 'staff_turf', 'user_id', 'turf_id')
                    ->withPivot('turf_admin_id')
                    ->withTimestamps();
    }

    /**
     * Get the Turf Admin's ID if the user is staff, or their own ID if they are Turf Admin.
     */
    public function getOwnerId()
    {
        if ($this->hasRole('turf-admin')) {
            return $this->id;
        }

        $assignment = $this->staffAssignments()->first();
        return $assignment ? $assignment->turf_admin_id : $this->id;
    }

    /**
     * Get a query builder of manageable locations for this user.
     */
    public function manageableLocations()
    {
        return Location::where(function ($query) {
            $query->where('user_id', $this->id)
                ->orWhereHas('turfs', function ($q) {
                    $q->whereIn('turfs.id', $this->assignedTurfs()->pluck('turfs.id'));
                });
        });
    }

    /**
     * Get a query builder of manageable turfs for this user.
     */
    public function manageableTurfs()
    {
        return Turf::where(function ($query) {
            $query->whereHas('location', function ($q) {
                $q->where('user_id', $this->id);
            })->orWhereIn('turfs.id', $this->assignedTurfs()->pluck('turfs.id'));
        });
    }
}
