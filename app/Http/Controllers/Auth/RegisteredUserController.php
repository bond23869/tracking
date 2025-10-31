<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        return DB::transaction(function () use ($request) {
            // Create organization for the new user
            $organization = Organization::create([
                'name' => explode('@', $request->email)[0] . "'s Organization",
                'slug' => Str::slug(explode('@', $request->email)[0] . '-' . Str::random(6)),
                'settings' => json_encode([]),
                'billing_status' => 'active',
                'plan' => 'free',
            ]);

            // Create user with organization
            $user = User::create([
                'name' => 'User', // Default name since we removed the name field from registration
                'email' => $request->email,
                'password' => $request->password,
                'organization_id' => $organization->id,
            ]);

            // Assign admin role to the user (they're the organization owner)
            $user->assignRole('admin');

            event(new Registered($user));

            Auth::login($user);

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        });
    }
}
