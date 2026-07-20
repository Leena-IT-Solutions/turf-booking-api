<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle user login by email or mobile.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->input('login');
        // If login input contains @, assume email, otherwise assume mobile
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        $user = User::where($field, $login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'roles' => $user->roles()->pluck('name'),
            ]
        ]);
    }

    /**
     * Handle user logout (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles');
        return response()->json($user);
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $email = $request->input('email');
        $mobile = $request->input('mobile');

        // Check if a quick created user matches this email or mobile
        $existingUser = null;
        if ($email) {
            $existingUser = User::where('email', $email)->first();
        }
        if (!$existingUser && $mobile) {
            $existingUser = User::where('mobile', $mobile)->first();
        }

        if ($existingUser && $existingUser->is_quick_created) {
            // Check if the other field is already taken by a DIFFERENT fully-registered user
            if ($email && $existingUser->email !== $email) {
                if (User::where('email', $email)->where('id', '!=', $existingUser->id)->exists()) {
                    return response()->json(['message' => 'The email has already been taken.'], 422);
                }
            }
            if ($mobile && $existingUser->mobile !== $mobile) {
                if (User::where('mobile', $mobile)->where('id', '!=', $existingUser->id)->exists()) {
                    return response()->json(['message' => 'The mobile has already been taken.'], 422);
                }
            }

            // Validate without unique rules since we will update this existing record
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'mobile' => 'required|string|max:15',
                'password' => 'required|string|min:6|confirmed',
            ]);

            // Update user to fully registered state
            $existingUser->update([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),
                'is_quick_created' => false,
            ]);

            $token = $existingUser->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $existingUser->id,
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                    'mobile' => $existingUser->mobile,
                    'roles' => $existingUser->roles()->pluck('name'),
                ]
            ], 201);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile' => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('customer');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'roles' => $user->roles()->pluck('name'),
            ]
        ], 201);
    }

    /**
     * Request a password reset OTP.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $email = $request->email;
        $otp = strval(rand(100000, 999999));

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now()
            ]
        );

        return response()->json([
            'message' => 'OTP sent successfully to your email.',
            'otp' => $otp, // Return in response for development convenience
        ]);
    }

    /**
     * Verify the password reset OTP.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|min:6|max:6',
        ]);

        $reset = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->otp, $reset->token)) {
            return response()->json([
                'message' => 'Invalid OTP code.'
            ], 422);
        }

        if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            return response()->json([
                'message' => 'OTP has expired.'
            ], 422);
        }

        return response()->json([
            'message' => 'OTP verified successfully.'
        ]);
    }

    /**
     * Reset the user password using OTP.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|min:6|max:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $reset = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->otp, $reset->token)) {
            return response()->json([
                'message' => 'Invalid OTP code.'
            ], 422);
        }

        if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            return response()->json([
                'message' => 'OTP has expired.'
            ], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->save();

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password reset successfully.'
        ]);
    }

    /**
     * Update user profile details.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'mobile' => 'required|string|max:15|unique:users,mobile,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'roles' => $user->roles()->pluck('name'),
            ]
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password does not match.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }

    /**
     * Delete user account.
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.'
        ]);
    }

    /**
     * Search users by Name, Email, or Mobile (restricted to admins/managers).
     */
    public function searchUsers(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['saas-admin', 'turf-admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = $request->query('query', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('email', 'LIKE', "%{$query}%")
              ->orWhere('mobile', 'LIKE', "%{$query}%");
        })
        ->limit(15)
        ->get(['id', 'name', 'email', 'mobile']);

        return response()->json($users);
    }

    /**
     * Quick create user by Owner or Manager.
     */
    public function quickCreateUser(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['saas-admin', 'turf-admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'mobile' => 'nullable|string|max:15',
        ]);

        $email = $request->input('email');
        $mobile = $request->input('mobile');

        if (!$email && !$mobile) {
            return response()->json([
                'message' => 'Either email or mobile number is required.'
            ], 422);
        }

        if ($email && User::where('email', $email)->exists()) {
            return response()->json(['message' => 'A user with this email already exists.'], 422);
        }

        if ($mobile && User::where('mobile', $mobile)->exists()) {
            return response()->json(['message' => 'A user with this mobile number already exists.'], 422);
        }

        $newUser = User::create([
            'name' => $request->name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => Hash::make(\Illuminate\Support\Str::random(16)),
            'is_quick_created' => true,
        ]);

        $newUser->assignRole('customer');

        return response()->json([
            'id' => $newUser->id,
            'name' => $newUser->name,
            'email' => $newUser->email,
            'mobile' => $newUser->mobile,
            'is_quick_created' => true,
        ], 201);
    }
}
