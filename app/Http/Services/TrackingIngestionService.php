<?php

namespace App\Http\Services;

use App\Models\IngestionToken;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingIngestionService
{
    /**
     * Ingest a tracking event.
     *
     * @return array{event_id: int, customer_id: int|null, session_id: int|null}
     */
    public function ingestEvent(
        Website $website,
        IngestionToken $ingestionToken,
        array $eventData,
        string $ip,
        ?string $userAgent
    ): array {
        Log::debug('Starting event ingestion', [
            'website_id' => $website->id,
            'event' => $eventData['event'] ?? null,
            'idempotency_key' => $eventData['idempotency_key'] ?? null,
        ]);

        return DB::transaction(function () use ($website, $ingestionToken, $eventData, $ip, $userAgent) {
            try {
                // 1. Resolve identity and customer
                Log::debug('Step 1: Resolving customer');
                $customer = $this->resolveCustomer($website, $eventData);
                Log::debug('Customer resolved', [
                    'customer_id' => $customer?->id ?? null,
                ]);

                // 2. Normalize UTM parameters (all UTM params use custom UTM system) and acquisition data
                Log::debug('Step 2: Normalizing UTM parameters and acquisition data');
                $customUtmValues = $this->normalizeCustomUtmParameters($website, $eventData);
                $referrerDomain = $this->normalizeReferrerDomain($website, $eventData['referrer'] ?? null);
                $landingPage = $this->normalizeLandingPage($website, $eventData['url'] ?? null);
                Log::debug('UTM and acquisition data normalized', [
                    'custom_utm_value_ids' => $customUtmValues,
                    'referrer_domain_id' => $referrerDomain,
                    'landing_page_id' => $landingPage,
                ]);

                // 3. Sessionize (find or create session)
                Log::debug('Step 3: Resolving session');
                $session = $this->resolveSession(
                    website: $website,
                    customer: $customer,
                    eventData: $eventData,
                    customUtmValues: $customUtmValues,
                    referrerDomain: $referrerDomain,
                    landingPage: $landingPage,
                    ip: $ip,
                    userAgent: $userAgent,
                );
                Log::debug('Session resolved', [
                    'session_id' => $session?->id ?? null,
                ]);

                // 4. Create or update touch if this is a new acquisition touchpoint
                Log::debug('Step 4: Resolving touch');
                $touch = $this->resolveTouch(
                    website: $website,
                    customer: $customer,
                    session: $session,
                    eventData: $eventData,
                    customUtmValues: $customUtmValues,
                    referrerDomain: $referrerDomain,
                    landingPage: $landingPage,
                );
                Log::debug('Touch resolved', [
                    'touch_id' => $touch?->id ?? null,
                ]);

                // 5. Create event with idempotency check
                Log::debug('Step 5: Creating event');
                $event = $this->createEvent(
                    website: $website,
                    customer: $customer,
                    session: $session,
                    ingestionToken: $ingestionToken,
                    eventData: $eventData,
                    customUtmValues: $customUtmValues,
                    referrerDomain: $referrerDomain,
                    landingPage: $landingPage,
                    touch: $touch,
                );
                Log::debug('Event created', [
                    'event_id' => $event->id ?? null,
                ]);

                // 6. Handle conversion attribution if this is a conversion event
                if ($this->isConversionEvent($eventData['event'])) {
                    Log::debug('Step 6: Creating conversion');
                    $this->createConversion(
                        website: $website,
                        customer: $customer,
                        session: $session,
                        event: $event,
                        eventData: $eventData,
                    );
                    Log::debug('Conversion created');
                }

                $result = [
                    'event_id' => $event->id,
                    'customer_id' => $customer?->id,
                    'session_id' => $session?->id,
                ];

                Log::debug('Event ingestion completed successfully', $result);

                return $result;
            } catch (\Exception $e) {
                Log::error('Error during event ingestion', [
                    'website_id' => $website->id,
                    'event' => $eventData['event'] ?? null,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Resolve customer from identity data.
     */
    protected function resolveCustomer(Website $website, array $eventData): ?object
    {
        $identityData = $eventData['identity'] ?? null;
        $customerId = $eventData['customer_id'] ?? null;

        // If customer_id is provided and valid, return it
        if ($customerId) {
            $customer = DB::table('customers')
                ->where('website_id', $website->id)
                ->where('id', $customerId)
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        // Resolve via identity
        if (!$identityData) {
            // Generate anonymous identity from IP + User-Agent (fallback)
            return null;
        }

        $identityType = $identityData['type'];
        $identityValue = $identityData['value'];
        $identityValueHash = hash('sha256', $identityValue);

        // Find or create identity
        $identity = DB::table('identities')
            ->where('website_id', $website->id)
            ->where('type', $identityType)
            ->where('value_hash', $identityValueHash)
            ->first();

        if (!$identity) {
            $identityId = DB::table('identities')->insertGetId([
                'website_id' => $website->id,
                'type' => $identityType,
                'value_hash' => $identityValueHash,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $identity = (object) ['id' => $identityId];
        } else {
            DB::table('identities')
                ->where('id', $identity->id)
                ->update(['last_seen_at' => now(), 'updated_at' => now()]);
        }

        // Find customer linked to this identity
        $link = DB::table('customer_identity_links')
            ->where('identity_id', $identity->id)
            ->first();

        if ($link) {
            $customer = DB::table('customers')
                ->where('id', $link->customer_id)
                ->first();
            
            if ($customer) {
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['last_seen_at' => now(), 'updated_at' => now()]);
                return $customer;
            }
        }

        // Create new customer
        $customerId = DB::table('customers')->insertGetId([
            'website_id' => $website->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link identity to customer
        DB::table('customer_identity_links')->insert([
            'customer_id' => $customerId,
            'identity_id' => $identity->id,
            'confidence' => 1.0,
            'source' => $identityType === 'user_id' ? 'login' : 'sdk',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('customers')->find($customerId);
    }

    /**
     * Normalize all UTM parameters (including standard ones) using the custom UTM system.
     * Returns array of custom_utm_value IDs.
     * 
     * @return array<int> Array of custom_utm_value IDs
     */
    protected function normalizeCustomUtmParameters(Website $website, array $eventData): array
    {
        $customUtmValueIds = [];

        // Extract ALL UTM parameters from event data (including standard ones like utm_source, utm_medium, etc.)
        foreach ($eventData as $key => $value) {
            // Check if it's a UTM parameter (starts with 'utm_')
            if (str_starts_with($key, 'utm_') && is_string($value) && !empty($value)) {
                // Extract parameter name (e.g., 'ad_id' from 'utm_ad_id')
                $paramName = substr($key, 4); // Remove 'utm_' prefix
                
                // Find or create custom UTM parameter
                $customUtmParam = DB::table('custom_utm_parameters')
                    ->where('website_id', $website->id)
                    ->where('name', $paramName)
                    ->first();

                if (!$customUtmParam) {
                    $customUtmParamId = DB::table('custom_utm_parameters')->insertGetId([
                        'website_id' => $website->id,
                        'name' => $paramName,
                        'first_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $customUtmParam = (object) ['id' => $customUtmParamId];
                }

                // Find or create custom UTM value
                $customUtmValue = DB::table('custom_utm_values')
                    ->where('custom_utm_parameter_id', $customUtmParam->id)
                    ->where('website_id', $website->id)
                    ->where('value', $value)
                    ->first();

                if (!$customUtmValue) {
                    $customUtmValueId = DB::table('custom_utm_values')->insertGetId([
                        'custom_utm_parameter_id' => $customUtmParam->id,
                        'website_id' => $website->id,
                        'value' => $value,
                        'first_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $customUtmValueIds[] = $customUtmValueId;
                } else {
                    $customUtmValueIds[] = $customUtmValue->id;
                }
            }
        }

        return $customUtmValueIds;
    }

    /**
     * Normalize referrer domain.
     */
    protected function normalizeReferrerDomain(Website $website, ?string $referrerUrl): ?int
    {
        if (!$referrerUrl) {
            return null;
        }

        $domain = parse_url($referrerUrl, PHP_URL_HOST);
        if (!$domain) {
            return null;
        }

        $referrerDomain = DB::table('referrer_domains')
            ->where('website_id', $website->id)
            ->where('domain', $domain)
            ->first();

        if (!$referrerDomain) {
            return DB::table('referrer_domains')->insertGetId([
                'website_id' => $website->id,
                'domain' => $domain,
                'category' => $this->categorizeReferrer($domain),
                'first_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $referrerDomain->id;
    }

    /**
     * Normalize landing page.
     */
    protected function normalizeLandingPage(Website $website, ?string $url): ?int
    {
        if (!$url) {
            return null;
        }

        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? '';
        $queryHash = $query ? hash('sha256', $query) : '';

        $landingPage = DB::table('landing_pages')
            ->where('website_id', $website->id)
            ->where('path', $path)
            ->where('query_hash', $queryHash)
            ->first();

        if (!$landingPage) {
            return DB::table('landing_pages')->insertGetId([
                'website_id' => $website->id,
                'path' => $path,
                'query_hash' => $queryHash,
                'full_url_sample' => strlen($url) > 500 ? substr($url, 0, 500) : $url,
                'first_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $landingPage->id;
    }

    /**
     * Resolve or create session.
     */
    protected function resolveSession(
        Website $website,
        ?object $customer,
        array $eventData,
        array $customUtmValues,
        ?int $referrerDomain,
        ?int $landingPage,
        string $ip,
        ?string $userAgent
    ): ?object {
        $sessionId = $eventData['session_id'] ?? null;
        
        if ($sessionId) {
            $session = DB::table('sessions_tracking')
                ->where('id', $sessionId)
                ->where('website_id', $website->id)
                ->first();

            if ($session && $this->isSessionActive($session)) {
                // Link custom UTM values to existing session
                $this->linkCustomUtmValuesToSession($session->id, $customUtmValues);
                return $session;
            }
        }

        if (!$customer) {
            return null;
        }

        // Check if there's an active session within timeout (30 minutes default)
        $activeSession = DB::table('sessions_tracking')
            ->where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->whereNull('ended_at')
            ->where('started_at', '>', now()->subMinutes(30))
            ->orderBy('started_at', 'desc')
            ->first();

        if ($activeSession && !$this->shouldBreakSession($activeSession)) {
            // Link custom UTM values to existing active session
            $this->linkCustomUtmValuesToSession($activeSession->id, $customUtmValues);
            return $activeSession;
        }

        // Create new session
        $sessionId = DB::table('sessions_tracking')->insertGetId([
            'website_id' => $website->id,
            'customer_id' => $customer->id,
            'started_at' => Carbon::parse($eventData['timestamp'] ?? now()),
            'landing_page_id' => $landingPage,
            'referrer_domain_id' => $referrerDomain,
            'landing_url' => $eventData['url'] ?? null,
            'referrer_url' => $eventData['referrer'] ?? null,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'is_bot' => $this->isBot($userAgent),
            'is_bounced' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link custom UTM values to session
        $this->linkCustomUtmValuesToSession($sessionId, $customUtmValues);

        return DB::table('sessions_tracking')->find($sessionId);
    }

    /**
     * Check if session should break due to campaign change.
     */
    protected function shouldBreakSession(object $session): bool
    {
        // Note: UTM parameters are not stored in sessions_tracking table
        // Session breaks are handled by timeout and referrer changes
        // For now, we don't break sessions based on UTM changes
        return false;
    }

    /**
     * Check if session is still active.
     */
    protected function isSessionActive(object $session): bool
    {
        if ($session->ended_at) {
            return false;
        }

        // Session is active if started within last 30 minutes
        return Carbon::parse($session->started_at)->isAfter(now()->subMinutes(30));
    }

    /**
     * Resolve or create touch for acquisition.
     */
    protected function resolveTouch(
        Website $website,
        ?object $customer,
        ?object $session,
        array $eventData,
        array $customUtmValues,
        ?int $referrerDomain,
        ?int $landingPage
    ): ?object {
        if (!$customer || !$session) {
            return null;
        }

        // Check if touch already exists for this session
        $existingTouch = DB::table('touches')
            ->where('session_id', $session->id)
            ->where('type', 'landing')
            ->first();

        if ($existingTouch) {
            // Link custom UTM values to existing touch
            $this->linkCustomUtmValuesToTouch($existingTouch->id, $customUtmValues);
            return $existingTouch;
        }

        // Only create touch if there's marketing data (UTMs or referrer)
        // Check if we have any UTM parameters or referrer
        $hasUtmParams = !empty($customUtmValues);
        if ($hasUtmParams || $referrerDomain) {
            $touchId = DB::table('touches')->insertGetId([
                'website_id' => $website->id,
                'customer_id' => $customer->id,
                'session_id' => $session->id,
                'occurred_at' => Carbon::parse($session->started_at),
                'type' => 'landing',
                'referrer_domain_id' => $referrerDomain,
                'landing_page_id' => $landingPage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update customer's first/last touch if needed
            $this->updateCustomerTouches($customer, $touchId);

            // Link custom UTM values to touch
            $this->linkCustomUtmValuesToTouch($touchId, $customUtmValues);

            return DB::table('touches')->find($touchId);
        }

        return null;
    }

    /**
     * Update customer's first and last touch references.
     */
    protected function updateCustomerTouches(object $customer, int $touchId): void
    {
        $updates = [];

        if (!$customer->first_touch_id) {
            $updates['first_touch_id'] = $touchId;
        }

        $updates['last_touch_id'] = $touchId;
        $updates['updated_at'] = now();

        if (!empty($updates)) {
            DB::table('customers')
                ->where('id', $customer->id)
                ->update($updates);
        }
    }

    /**
     * Create event with idempotency check.
     */
    protected function createEvent(
        Website $website,
        ?object $customer,
        ?object $session,
        IngestionToken $ingestionToken,
        array $eventData,
        array $customUtmValues,
        ?int $referrerDomain,
        ?int $landingPage,
        ?object $touch
    ): object {
        $idempotencyKey = $eventData['idempotency_key'];

        // Check for existing event
        $existing = DB::table('event_dedup_keys')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            // Link custom UTM values to existing event (in case of duplicates with different UTM params)
            $this->linkCustomUtmValuesToEvent($existing->event_id, $customUtmValues);
            return DB::table('events')->find($existing->event_id);
        }

        $occurredAt = $eventData['timestamp'] 
            ? Carbon::parse($eventData['timestamp']) 
            : now();

        $eventId = DB::table('events')->insertGetId([
            'website_id' => $website->id,
            'session_id' => $session?->id,
            'customer_id' => $customer?->id,
            'name' => $eventData['event'],
            'occurred_at' => $occurredAt,
            'props' => json_encode($eventData['properties'] ?? []),
            'revenue_cents' => isset($eventData['revenue']) 
                ? (int) round($eventData['revenue'] * 100) 
                : null,
            'currency' => $eventData['currency'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'ingestion_token_id' => $ingestionToken->id,
            'schema_version' => $eventData['schema_version'] ?? 1,
            'sdk_version' => $eventData['sdk_version'] ?? null,
            'referrer_domain_id' => $referrerDomain,
            'landing_page_id' => $landingPage,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Record idempotency key
        DB::table('event_dedup_keys')->insert([
            'idempotency_key' => $idempotencyKey,
            'event_id' => $eventId,
            'created_at' => now(),
        ]);

        // Link custom UTM values to event
        $this->linkCustomUtmValuesToEvent($eventId, $customUtmValues);

        return DB::table('events')->find($eventId);
    }

    /**
     * Create conversion with attribution.
     */
    protected function createConversion(
        Website $website,
        ?object $customer,
        ?object $session,
        object $event,
        array $eventData
    ): void {
        if (!$customer) {
            return;
        }

        // Find first and last non-direct touch
        $firstTouch = DB::table('touches')
            ->where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->orderBy('occurred_at', 'asc')
            ->first();

        // Find last non-direct touch by checking if there are custom UTM values or referrer
        // Note: Standard UTM parameters are not stored in touches table
        // We'll check for custom UTM values via junction table
        $lastNonDirectTouch = DB::table('touches')
            ->where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('referrer_domain_id') // Non-direct (has referrer)
            ->orderBy('occurred_at', 'desc')
            ->first();

        $attributedTouch = $lastNonDirectTouch ?? $firstTouch;

        DB::table('conversions')->insert([
            'website_id' => $website->id,
            'customer_id' => $customer->id,
            'session_id' => $session?->id,
            'event_id' => $event->id,
            'occurred_at' => Carbon::parse($event->occurred_at),
            'value_cents' => $event->revenue_cents,
            'currency' => $event->currency,
            'first_touch_id' => $firstTouch?->id,
            'last_non_direct_touch_id' => $lastNonDirectTouch?->id,
            'attributed_touch_id' => $attributedTouch?->id,
            'attribution_model' => 'last_non_direct',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Check if event is a conversion event.
     */
    protected function isConversionEvent(string $eventName): bool
    {
        $conversionEvents = ['purchase', 'order', 'conversion', 'checkout_completed'];
        return in_array(strtolower($eventName), $conversionEvents);
    }

    /**
     * Categorize referrer domain.
     */
    protected function categorizeReferrer(string $domain): string
    {
        $domain = strtolower($domain);

        $searchDomains = ['google.com', 'bing.com', 'yahoo.com', 'duckduckgo.com'];
        $socialDomains = ['facebook.com', 'twitter.com', 'instagram.com', 'linkedin.com', 'pinterest.com', 'tiktok.com'];

        if (in_array($domain, $searchDomains) || str_contains($domain, 'search')) {
            return 'search';
        }

        if (in_array($domain, $socialDomains) || str_contains($domain, 'social')) {
            return 'social';
        }

        if (str_contains($domain, 'mail') || str_contains($domain, 'email')) {
            return 'email';
        }

        return 'other';
    }

    /**
     * Link custom UTM values to session via junction table.
     */
    protected function linkCustomUtmValuesToSession(int $sessionId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('session_custom_utm_values')
                ->where('session_id', $sessionId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('session_custom_utm_values')->insert([
                    'session_id' => $sessionId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Link custom UTM values to event via junction table.
     */
    protected function linkCustomUtmValuesToEvent(int $eventId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('event_custom_utm_values')
                ->where('event_id', $eventId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('event_custom_utm_values')->insert([
                    'event_id' => $eventId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Link custom UTM values to touch via junction table.
     */
    protected function linkCustomUtmValuesToTouch(int $touchId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('touch_custom_utm_values')
                ->where('touch_id', $touchId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('touch_custom_utm_values')->insert([
                    'touch_id' => $touchId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Simple bot detection.
     */
    protected function isBot(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        $botPatterns = ['bot', 'crawler', 'spider', 'scraper', 'googlebot', 'bingbot'];
        $ua = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
