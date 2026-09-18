<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Impersonate the owner of a specific turf.
     */
    public function impersonate(Request $request, Turf $turf)
    {
        $admin = $request->user();

        if (!$admin || !$admin->hasRole('saas-admin')) {
            abort(403, 'Unauthorized access.');
        }

        // Find the owner of this turf via its location
        $owner = $turf->location?->user;

        if (!$owner) {
            abort(404, 'No owner account associated with this venue.');
        }

        // Remember the original SaaS Admin in the session
        session([
            'impersonator_id' => $admin->id,
            'active_turf_id' => $turf->id,
            'active_location_id' => $turf->location_id,
        ]);

        // Authenticate as the turf owner
        Auth::login($owner);

        // Redirect directly to the Turf Admin dashboard
        return redirect()->route('turf.dashboard');
    }

    /**
     * Leave impersonation mode and return as SaaS Admin.
     */
    public function leave(Request $request)
    {
        $adminId = session('impersonator_id');

        if ($adminId) {
            $admin = User::find($adminId);

            if ($admin && $admin->hasRole('saas-admin')) {
                // Clear impersonation session variables
                session()->forget(['impersonator_id', 'active_turf_id', 'active_location_id']);

                // Re-login as the original SaaS Admin
                Auth::login($admin);

                return redirect()->route('saas.turfs');
            }
        }

        session()->forget(['impersonator_id', 'active_turf_id', 'active_location_id']);

        return redirect()->route('dashboard');
    }
}
