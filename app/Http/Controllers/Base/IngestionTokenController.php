<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Models\IngestionToken;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;

class IngestionTokenController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Website $website): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Check for duplicate name
        if ($website->ingestionTokens()->where('name', $validated['name'])->exists()) {
            return back()->withErrors(['name' => 'A token with this name already exists.']);
        }

        // Generate token
        $tokenPrefix = Str::random(12);
        $tokenSecret = Str::random(32);
        $fullToken = $tokenPrefix . '.' . $tokenSecret;
        $tokenHash = Hash::make($fullToken);

        $ingestionToken = $website->ingestionTokens()->create([
            'name' => $validated['name'],
            'token_prefix' => $tokenPrefix,
            'token_hash' => $tokenHash,
        ]);

        // Store the plain token in session to display it once
        session()->flash('new_token', [
            'id' => $ingestionToken->id,
            'name' => $ingestionToken->name,
            'token' => $fullToken,
        ]);

        return back()->with('success', 'Token created successfully. Save it now - it cannot be retrieved later!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website, IngestionToken $ingestionToken): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Ensure the token belongs to the website
        if ($ingestionToken->website_id !== $website->id) {
            abort(404, 'Token not found for this website.');
        }

        $ingestionToken->delete();

        return back()->with('success', 'Token deleted successfully.');
    }

    /**
     * Revoke the specified token.
     */
    public function revoke(Website $website, IngestionToken $ingestionToken): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Ensure the token belongs to the website
        if ($ingestionToken->website_id !== $website->id) {
            abort(404, 'Token not found for this website.');
        }

        $ingestionToken->update(['revoked_at' => now()]);

        return back()->with('success', 'Token revoked successfully.');
    }

    /**
     * Restore a revoked token.
     */
    public function restore(Website $website, IngestionToken $ingestionToken): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Ensure the token belongs to the website
        if ($ingestionToken->website_id !== $website->id) {
            abort(404, 'Token not found for this website.');
        }

        $ingestionToken->update(['revoked_at' => null]);

        return back()->with('success', 'Token restored successfully.');
    }
}
