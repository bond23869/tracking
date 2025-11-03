<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $organization = Auth::user()->organization;
        
        $accounts = $organization->accounts()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'archived_at' => $account->archived_at?->toISOString(),
                    'is_archived' => $account->isArchived(),
                    'created_at' => $account->created_at->toISOString(),
                    'updated_at' => $account->updated_at->toISOString(),
                ];
            });

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $organization = Auth::user()->organization;

        // Generate a unique slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        
        while ($organization->accounts()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $account = $organization->accounts()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return back()->with('success', 'Account created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account): RedirectResponse
    {
        $organization = Auth::user()->organization;

        // Ensure the account belongs to the organization
        if ($account->organization_id !== $organization->id) {
            abort(404, 'Account not found in your organization.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $account->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Account updated successfully.');
    }

    /**
     * Archive the specified resource.
     */
    public function archive(Account $account): RedirectResponse
    {
        $organization = Auth::user()->organization;

        // Ensure the account belongs to the organization
        if ($account->organization_id !== $organization->id) {
            abort(404, 'Account not found in your organization.');
        }

        $account->archive();

        return back()->with('success', 'Account archived successfully.');
    }

    /**
     * Switch the current account in the session.
     */
    public function switch(Account $account): RedirectResponse
    {
        $organization = Auth::user()->organization;

        // Ensure the account belongs to the organization
        if ($account->organization_id !== $organization->id) {
            abort(404, 'Account not found in your organization.');
        }

        // Ensure the account is not archived
        if ($account->isArchived()) {
            return back()->withErrors(['account' => 'Cannot switch to an archived account.']);
        }

        // Store the current account ID in the session
        session(['current_account_id' => $account->id]);

        return back();
    }
}
