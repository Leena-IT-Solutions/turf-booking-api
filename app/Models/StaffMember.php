<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_admin_id',
        'user_id',
        'role',
    ];

    /**
     * Get the Turf Admin who added this staff member.
     */
    public function turfAdmin()
    {
        return $this->belongsTo(User::class, 'turf_admin_id');
    }

    /**
     * Get the User assigned to the staff role.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
