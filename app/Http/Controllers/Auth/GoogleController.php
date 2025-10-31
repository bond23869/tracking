<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Unable to authenticate with Google. Please try again.'
            ]);
        }

        return DB::transaction(function () use ($googleUser) {
            // Check if user exists with this Google ID
            $user = User::where('google_id', $googleUser->getId())->first();

            if ($user) {
                // Update user's avatar if it has changed
                if ($user->avatar !== $googleUser->getAvatar()) {
                    $user->update(['avatar' => $googleUser->getAvatar()]);
                }
                
                Auth::login($user);
                return redirect()->intended(route('dashboard'));
            }

            // Check if user exists with this email
            $existingUser = User::where('email', $googleUser->getEmail())->first();

            if ($existingUser) {
                // Link Google account to existing user
                $existingUser->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
                
                Auth::login($existingUser);
                return redirect()->intended(route('dashboard'));
            }

            // Create new user and organization
            $organization = Organization::create([
                'name' => explode('@', $googleUser->getEmail())[0] . "'s Organization",
                'slug' => Str::slug(explode('@', $googleUser->getEmail())[0] . '-' . Str::random(6)),
                'settings' => json_encode([]),
                'billing_status' => 'active',
                'plan' => 'free',
            ]);

            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'organization_id' => $organization->id,
                'email_verified_at' => now(), // Google accounts are pre-verified
            ]);

            // Assign admin role to the user (they're the organization owner)
            $user->assignRole('admin');

            event(new Registered($user));

            Auth::login($user);

            return redirect()->intended(route('dashboard'));
        });
    }
}
