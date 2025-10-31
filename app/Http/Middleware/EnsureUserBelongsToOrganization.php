<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is not authenticated, let other middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Skip organization checks for onboarding routes to prevent redirect loops
        if ($this->isOnboardingRoute($request)) {
            return $next($request);
        }

        // If user doesn't have an organization, redirect to onboarding
        if (!$user->organization_id) {
            return redirect()->route('onboarding.index');
        }

        // Check onboarding completion
        if (!$user->hasCompletedOnboarding()) {
            return redirect()->route('onboarding.index');
        }

        // Add organization context to the request
        $request->merge(['organization' => $user->organization]);

        return $next($request);
    }

    /**
     * Check if the current request is for an onboarding route.
     */
    private function isOnboardingRoute(Request $request): bool
    {
        return $request->routeIs('onboarding.*');
    }
}
