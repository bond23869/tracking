<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class WebsiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $organization = $user->organization;
        
        // Get current account from session
        $currentAccountId = session('current_account_id');
        
        if (!$currentAccountId) {
            return Inertia::render('websites/index', [
                'websites' => [],
                'currentAccount' => null,
                'showArchived' => $request->boolean('show_archived', false),
            ]);
        }

        $currentAccount = $organization->accounts()
            ->active()
            ->find($currentAccountId);

        if (!$currentAccount) {
            return Inertia::render('websites/index', [
                'websites' => [],
                'currentAccount' => null,
                'showArchived' => $request->boolean('show_archived', false),
            ]);
        }

        // Check if we should show archived websites
        $showArchived = $request->boolean('show_archived', false);
        
        $websitesQuery = $currentAccount->websites();
        
        if ($showArchived) {
            // Show all websites (archived and active)
            $websitesQuery->orderBy('archived_at', 'asc')
                ->orderBy('created_at', 'desc');
        } else {
            // Only show active websites
            $websitesQuery->active()
                ->orderBy('created_at', 'desc');
        }

        $websites = $websitesQuery->get()
            ->map(function ($website) {
                return [
                    'id' => $website->id,
                    'name' => $website->name,
                    'url' => $website->url,
                    'type' => $website->type,
                    'status' => $website->status,
                    'connection_status' => $website->connection_status,
                    'connection_error' => $website->connection_error,
                    'archived_at' => $website->archived_at?->toISOString(),
                    'is_archived' => $website->isArchived(),
                    'created_at' => $website->created_at->toISOString(),
                    'updated_at' => $website->updated_at->toISOString(),
                ];
            });

        return Inertia::render('websites/index', [
            'websites' => $websites,
            'currentAccount' => [
                'id' => $currentAccount->id,
                'name' => $currentAccount->name,
                'slug' => $currentAccount->slug,
            ],
            'showArchived' => $showArchived,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Website $website): Response
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Load ingestion tokens
        $website->load('ingestionTokens');

        // Get new token from flash session if present
        $newToken = session('new_token');

        return Inertia::render('websites/show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'url' => $website->url,
                'type' => $website->type,
                'status' => $website->status,
                'connection_status' => $website->connection_status,
                'connection_error' => $website->connection_error,
                'archived_at' => $website->archived_at?->toISOString(),
                'is_archived' => $website->isArchived(),
                'created_at' => $website->created_at->toISOString(),
                'updated_at' => $website->updated_at->toISOString(),
                'ingestion_tokens' => $website->ingestionTokens->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'name' => $token->name,
                        'token_prefix' => $token->token_prefix,
                        'last_used_at' => $token->last_used_at?->toISOString(),
                        'expires_at' => $token->expires_at?->toISOString(),
                        'revoked_at' => $token->revoked_at?->toISOString(),
                        'is_revoked' => $token->revoked_at !== null,
                        'created_at' => $token->created_at->toISOString(),
                    ];
                })->values(),
                'new_token' => $newToken,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Website $website): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $website->update($validated);

        return back()->with('success', 'Website updated successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:woocommerce,shopify'],
            'url' => ['required', 'url', 'max:255'],
        ]);

        $user = Auth::user();
        $organization = $user->organization;
        
        // Get current account from session
        $currentAccountId = session('current_account_id');
        
        if (!$currentAccountId) {
            return back()->withErrors(['error' => 'No account selected.']);
        }

        $currentAccount = $organization->accounts()
            ->active()
            ->find($currentAccountId);

        if (!$currentAccount) {
            return back()->withErrors(['error' => 'Account not found.']);
        }

        // Generate a name from the URL
        $urlParts = parse_url($validated['url']);
        $name = $urlParts['host'] ?? 'Untitled Website';

        $website = $currentAccount->websites()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'url' => $validated['url'],
            'type' => $validated['type'],
            'status' => 'active',
            'connection_status' => 'disconnected',
        ]);

        return back()->with('success', 'Website created successfully.');
    }

    /**
     * Archive the specified resource.
     */
    public function archive(Website $website): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        $website->archive();

        return back()->with('success', 'Website archived successfully.');
    }

    /**
     * Unarchive the specified resource.
     */
    public function unarchive(Website $website): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        $website->unarchive();

        return back()->with('success', 'Website unarchived successfully.');
    }
}
