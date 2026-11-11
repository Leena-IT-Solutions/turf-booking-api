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

        if (!$admin || (!$admin->hasRole('saas-admin') && !$admin->hasRole('marketing'))) {
            abort(403, 'Unauthorized access.');
        }

        // Find the owner of this turf via its location
        $owner = $turf->location?->user;

        if (!$owner) {
            abort(404, 'No owner account associated with this venue.');
        }

        // Remember the original SaaS Admin / Marketing Executive in the session
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
     * Impersonate a specific user (Turf Owner) directly — even if they have 0 locations/turfs yet.
     */
    public function impersonateUser(Request $request, User $user)
    {
        $admin = $request->user();

        if (!$admin || (!$admin->hasRole('saas-admin') && !$admin->hasRole('marketing'))) {
            abort(403, 'Unauthorized access.');
        }

        // Marketing executives cannot impersonate SaaS Admin accounts
        if ($user->hasRole('saas-admin') && !$admin->hasRole('saas-admin')) {
            abort(403, 'Marketing executives cannot impersonate SaaS Admin accounts.');
        }

        if (!$user->hasRole('turf-admin') && !$user->hasRole('manager')) {
            $user->assignRole('turf-admin');
            $user->startTurfOwnerTrial();
        }

        $firstLocation = \App\Models\Location::where('user_id', $user->id)->with('turfs')->first();
        $firstTurf = $firstLocation?->turfs->first();

        session()->forget(['active_turf_id', 'active_location_id']);
        session([
            'impersonator_id' => $admin->id,
            'active_location_id' => $firstLocation?->id,
            'active_turf_id' => $firstTurf?->id,
        ]);

        Auth::login($user);

        if (!$firstLocation) {
            session()->flash('status', "Switched to {$user->name}'s workspace. Start by adding their first Location below.");
            return redirect()->route('turf.locations');
        }

        if (!$firstTurf) {
            session()->flash('status', "Switched to {$user->name}'s workspace. Now add their first Turf under {$firstLocation->name}.");
            return redirect()->route('turf.turfs');
        }

        return redirect()->route('turf.dashboard');
    }

    /**
     * Leave impersonation mode and return as SaaS Admin / Marketing Executive.
     */
    public function leave(Request $request)
    {
        $adminId = session('impersonator_id');

        if ($adminId) {
            $admin = User::find($adminId);

            if ($admin && ($admin->hasRole('saas-admin') || $admin->hasRole('marketing'))) {
                // Clear impersonation session variables
                session()->forget(['impersonator_id', 'active_turf_id', 'active_location_id']);

                // Re-login as the original SaaS Admin or Marketing Executive
                Auth::login($admin);

                return redirect()->route('saas.turfs');
            }
        }

        session()->forget(['impersonator_id', 'active_turf_id', 'active_location_id']);

        return redirect()->route('dashboard');
    }
}
