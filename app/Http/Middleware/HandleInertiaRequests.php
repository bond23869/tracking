<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $currentAccount = null;
        $accounts = [];

        if ($user && $user->organization) {
            $organization = $user->organization;
            
            // Get all active accounts for the organization
            $accounts = $organization->accounts()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'slug' => $account->slug,
                    ];
                })
                ->toArray();

            // Get current account from session or default to first account
            $currentAccountId = $request->session()->get('current_account_id');
            
            if ($currentAccountId) {
                $currentAccount = $organization->accounts()
                    ->active()
                    ->find($currentAccountId);
            }
            
            // If no current account or it's archived, use the first account
            if (!$currentAccount || $currentAccount->isArchived()) {
                $currentAccount = $organization->accounts()->active()->first();
                
                // Update session if we found a default account
                if ($currentAccount) {
                    $request->session()->put('current_account_id', $currentAccount->id);
                }
            }

            // Format current account if it exists
            if ($currentAccount) {
                $currentAccount = [
                    'id' => $currentAccount->id,
                    'name' => $currentAccount->name,
                    'slug' => $currentAccount->slug,
                ];
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'currentAccount' => $currentAccount,
            'accounts' => $accounts,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
