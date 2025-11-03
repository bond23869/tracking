<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsitePixel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class WebsitePixelController extends Controller
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

        $platform = $request->input('platform');
        
        if (!$platform) {
            return back()->withErrors(['platform' => 'Platform is required.']);
        }
        
        // Validate based on platform
        $rules = $this->getValidationRules($platform);
        $validated = $request->validate($rules);

        $website->pixels()->create($validated);

        return back()->with('success', 'Pixel created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Website $website, WebsitePixel $pixel): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Ensure the pixel belongs to the website
        if ($pixel->website_id !== $website->id) {
            abort(404, 'Pixel not found for this website.');
        }

        $platform = $pixel->platform;
        $rules = $this->getValidationRules($platform);
        $validated = $request->validate($rules);

        $pixel->update($validated);

        return back()->with('success', 'Pixel updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website, WebsitePixel $pixel): RedirectResponse
    {
        $user = Auth::user();
        $organization = $user->organization;

        // Ensure the website belongs to the organization
        if ($website->organization_id !== $organization->id) {
            abort(404, 'Website not found in your organization.');
        }

        // Ensure the pixel belongs to the website
        if ($pixel->website_id !== $website->id) {
            abort(404, 'Pixel not found for this website.');
        }

        $pixel->delete();

        return back()->with('success', 'Pixel deleted successfully.');
    }

    /**
     * Get validation rules based on platform.
     */
    private function getValidationRules(string $platform): array
    {
        $baseRules = [
            'platform' => ['required', 'string', 'in:meta,google,tiktok,pinterest,snapchat,x,klaviyo,reddit'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $platformRules = match($platform) {
            'meta' => [
                'pixel_id' => ['required', 'string'],
                'access_token' => ['required', 'string'],
            ],
            'google' => [
                'conversion_id' => ['required', 'string'],
                'conversion_labels' => ['sometimes', 'array'],
            ],
            'tiktok' => [
                'pixel_id' => ['required', 'string'],
                'access_token' => ['required', 'string'],
            ],
            'pinterest' => [
                'tag_id' => ['required', 'string'],
                'ad_account_id' => ['required', 'string'],
                'access_token' => ['required', 'string'],
            ],
            'snapchat' => [
                'snapchat_pixel_id' => ['required', 'string'],
                'access_token' => ['required', 'string'],
            ],
            'x' => [
                'pixel_id' => ['required', 'string'],
                'event_ids' => ['sometimes', 'array'],
            ],
            'klaviyo' => [
                'public_api_key' => ['required', 'string'],
                'private_api_key' => ['required', 'string'],
            ],
            'reddit' => [
                'pixel_id' => ['required', 'string'],
                'access_token' => ['required', 'string'],
            ],
            default => [],
        };

        return array_merge($baseRules, $platformRules);
    }
}
