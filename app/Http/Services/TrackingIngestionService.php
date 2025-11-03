<?php

namespace App\Http\Services;

use App\Models\IngestionToken;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        return DB::transaction(function () use ($website, $ingestionToken, $eventData, $ip, $userAgent) {
            // 1. Resolve identity and customer
            $customer = $this->resolveCustomer($website, $eventData);

            // 2. Normalize UTM dimensions and acquisition data
            $utmData = $this->normalizeUtmDimensions($website, $eventData);
            $referrerDomain = $this->normalizeReferrerDomain($website, $eventData['referrer'] ?? null);
            $landingPage = $this->normalizeLandingPage($website, $eventData['url'] ?? null);

            // 3. Sessionize (find or create session)
            $session = $this->resolveSession(
                website: $website,
                customer: $customer,
                eventData: $eventData,
                utmData: $utmData,
                referrerDomain: $referrerDomain,
                landingPage: $landingPage,
                ip: $ip,
                userAgent: $userAgent,
            );

            // 4. Create or update touch if this is a new acquisition touchpoint
            $touch = $this->resolveTouch(
                website: $website,
                customer: $customer,
                session: $session,
                eventData: $eventData,
                utmData: $utmData,
                referrerDomain: $referrerDomain,
                landingPage: $landingPage,
            );

            // 5. Create event with idempotency check
            $event = $this->createEvent(
                website: $website,
                customer: $customer,
                session: $session,
                ingestionToken: $ingestionToken,
                eventData: $eventData,
                utmData: $utmData,
                referrerDomain: $referrerDomain,
                landingPage: $landingPage,
                touch: $touch,
            );

            // 6. Handle conversion attribution if this is a conversion event
            if ($this->isConversionEvent($eventData['event'])) {
                $this->createConversion(
                    website: $website,
                    customer: $customer,
                    session: $session,
                    event: $event,
                    eventData: $eventData,
                );
            }

            return [
                'event_id' => $event->id,
                'customer_id' => $customer?->id,
                'session_id' => $session?->id,
            ];
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
     * Normalize UTM dimensions and return IDs.
     */
    protected function normalizeUtmDimensions(Website $website, array $eventData): array
    {
        $utmTables = [
            'source' => 'utm_sources',
            'medium' => 'utm_mediums',
            'campaign' => 'utm_campaigns',
            'term' => 'utm_terms',
            'content' => 'utm_contents',
        ];

        $result = [];

        foreach ($utmTables as $key => $table) {
            $value = $eventData['utm_' . $key] ?? null;
            
            if (!$value) {
                $result[$key . '_id'] = null;
                continue;
            }

            // Find or create
            $dimension = DB::table($table)
                ->where('website_id', $website->id)
                ->where('value', $value)
                ->first();

            if (!$dimension) {
                $result[$key . '_id'] = DB::table($table)->insertGetId([
                    'website_id' => $website->id,
                    'value' => $value,
                    'first_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $result[$key . '_id'] = $dimension->id;
            }
        }

        return $result;
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
        array $utmData,
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

        if ($activeSession && !$this->shouldBreakSession($activeSession, $utmData)) {
            return $activeSession;
        }

        // Create new session
        $sessionId = DB::table('sessions_tracking')->insertGetId([
            'website_id' => $website->id,
            'customer_id' => $customer->id,
            'started_at' => Carbon::parse($eventData['timestamp'] ?? now()),
            'landing_page_id' => $landingPage,
            'referrer_domain_id' => $referrerDomain,
            'utm_source_id' => $utmData['source_id'] ?? null,
            'utm_medium_id' => $utmData['medium_id'] ?? null,
            'utm_campaign_id' => $utmData['campaign_id'] ?? null,
            'utm_term_id' => $utmData['term_id'] ?? null,
            'utm_content_id' => $utmData['content_id'] ?? null,
            'landing_url' => $eventData['url'] ?? null,
            'referrer_url' => $eventData['referrer'] ?? null,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'is_bot' => $this->isBot($userAgent),
            'is_bounced' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('sessions_tracking')->find($sessionId);
    }

    /**
     * Check if session should break due to campaign change.
     */
    protected function shouldBreakSession(object $session, array $utmData): bool
    {
        // Break if UTM campaign changed
        if (($utmData['campaign_id'] ?? null) && $session->utm_campaign_id !== $utmData['campaign_id']) {
            return true;
        }

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
        array $utmData,
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
            return $existingTouch;
        }

        // Only create touch if there's marketing data (UTMs or referrer)
        if ($utmData['source_id'] || $utmData['medium_id'] || $referrerDomain) {
            $touchId = DB::table('touches')->insertGetId([
                'website_id' => $website->id,
                'customer_id' => $customer->id,
                'session_id' => $session->id,
                'occurred_at' => Carbon::parse($session->started_at),
                'type' => 'landing',
                'utm_source_id' => $utmData['source_id'] ?? null,
                'utm_medium_id' => $utmData['medium_id'] ?? null,
                'utm_campaign_id' => $utmData['campaign_id'] ?? null,
                'utm_term_id' => $utmData['term_id'] ?? null,
                'utm_content_id' => $utmData['content_id'] ?? null,
                'referrer_domain_id' => $referrerDomain,
                'landing_page_id' => $landingPage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update customer's first/last touch if needed
            $this->updateCustomerTouches($customer, $touchId);

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
        array $utmData,
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
            'utm_source_id' => $utmData['source_id'] ?? null,
            'utm_medium_id' => $utmData['medium_id'] ?? null,
            'utm_campaign_id' => $utmData['campaign_id'] ?? null,
            'utm_term_id' => $utmData['term_id'] ?? null,
            'utm_content_id' => $utmData['content_id'] ?? null,
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

        $lastNonDirectTouch = DB::table('touches')
            ->where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('utm_source_id') // Non-direct
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
