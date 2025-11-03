<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $organization = $user->organization;
        
        // Get current account from session
        $currentAccountId = session('current_account_id');
        
        if (!$currentAccountId) {
            return Inertia::render('websites/index', [
                'websites' => [],
                'currentAccount' => null,
            ]);
        }

        $currentAccount = $organization->accounts()
            ->active()
            ->find($currentAccountId);

        if (!$currentAccount) {
            return Inertia::render('websites/index', [
                'websites' => [],
                'currentAccount' => null,
            ]);
        }

        $websites = $currentAccount->websites()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($website) {
                return [
                    'id' => $website->id,
                    'name' => $website->name,
                    'url' => $website->url,
                    'status' => $website->status,
                    'connection_status' => $website->connection_status,
                    'connection_error' => $website->connection_error,
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
        ]);
    }
}
