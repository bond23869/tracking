<?php

namespace App\Http\Services;

use App\Jobs\ProcessEventIngestion;
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
     * Ingest event: Check idempotency and queue processing job.
     * All processing (including event storage) happens in the queue worker.
     *
     * @return array{event_id: int|null}
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

        // Check idempotency synchronously to prevent duplicate jobs
        $idempotencyKey = $eventData['idempotency_key'];
        $existing = DB::table('events')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            Log::debug('Event already exists (idempotency)', [
                'event_id' => $existing->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            return [
                'event_id' => $existing->id,
            ];
        }

        // Queue the job - it will handle everything: storage + processing
        ProcessEventIngestion::dispatch(
            eventId: null, // Will be created by the job
            websiteId: $website->id,
            ingestionTokenId: $ingestionToken->id,
            eventData: $eventData,
            ip: $ip,
            userAgent: $userAgent
        );

        Log::debug('Event queued for processing', [
            'idempotency_key' => $idempotencyKey,
        ]);

        return [
            'event_id' => null, // Will be available after job processes
        ];
    }

    /**
     * Store event with minimal processing (fast path).
     * Only performs idempotency check and stores the event.
     *
     * @return int The event ID
     */
    protected function storeEventFast(
        Website $website,
        IngestionToken $ingestionToken,
        array $eventData
    ): int {
        $idempotencyKey = $eventData['idempotency_key'];

        // Check for existing event (idempotency)
        $existing = DB::table('events')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            Log::debug('Event already exists (idempotency)', [
                'event_id' => $existing->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            return $existing->id;
        }

        // Note: session_id is now required, but we'll need to create/get session first
        // For now, throw an error if session_id is not provided
        // This method should only be called after session is created
        throw new \RuntimeException('storeEventFast requires a session_id. Use processEventAsync instead.');
    }

    /**
     * Process event asynchronously - handles everything in the queue worker.
     * Creates event if eventId is null, then processes everything:
     * - Event storage (if needed)
     * - Customer resolution
     * - Session management
     * - Touch attribution
     * - Conversion handling
     * - UTM normalization
     */
    public function processEventAsync(
        ?int $eventId,
        Website $website,
        IngestionToken $ingestionToken,
        array $eventData,
        string $ip,
        ?string $userAgent
    ): void {
        Log::debug('Starting async event processing', [
            'event_id' => $eventId,
            'website_id' => $website->id,
            'event' => $eventData['event'] ?? null,
        ]);

        DB::transaction(function () use (&$eventId, $website, $ingestionToken, $eventData, $ip, $userAgent) {
            try {
                // Check idempotency first (if eventId is provided, use it; otherwise check by key)
                $idempotencyKey = $eventData['idempotency_key'];
                if ($eventId) {
                    $existing = DB::table('events')->find($eventId);
                    if ($existing && $existing->idempotency_key !== $idempotencyKey) {
                        Log::warning('Event ID provided but idempotency key mismatch', [
                            'event_id' => $eventId,
                            'expected_key' => $idempotencyKey,
                            'actual_key' => $existing->idempotency_key,
                        ]);
                    }
                } else {
                    // Check idempotency by key (race condition protection in queue)
                    $existing = DB::table('events')
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    
                    if ($existing) {
                        Log::debug('Event already exists (idempotency check in queue)', [
                            'event_id' => $existing->id,
                            'idempotency_key' => $idempotencyKey,
                        ]);
                        $eventId = $existing->id;
                    }
                }

                // 1. Resolve identity and customer
                Log::debug('Step 1: Resolving customer', ['event_id' => $eventId]);
                $customer = $this->resolveCustomer($website, $eventData, $ip, $userAgent);
                Log::debug('Customer resolved', [
                    'event_id' => $eventId,
                    'customer_id' => $customer?->id ?? null,
                ]);

                // 2. Normalize UTM parameters (all UTM params use custom UTM system) and acquisition data
                Log::debug('Step 2: Normalizing UTM parameters and acquisition data', ['event_id' => $eventId]);
                $customUtmValues = $this->normalizeCustomUtmParameters($website, $eventData);
                $referrerDomain = $this->normalizeReferrerDomain($website, $eventData['referrer'] ?? null);
                $landingPage = $this->normalizeLandingPage($website, $eventData['url'] ?? null);
                Log::debug('UTM and acquisition data normalized', [
                    'event_id' => $eventId,
                    'custom_utm_value_ids' => $customUtmValues,
                    'referrer_domain_id' => $referrerDomain,
                    'landing_page_id' => $landingPage,
                ]);

                // 3. Sessionize (find or create session) - REQUIRED for event creation
                Log::debug('Step 3: Resolving session', ['event_id' => $eventId]);
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
                
                if (!$session) {
                    Log::error('Cannot create event without session', [
                        'website_id' => $website->id,
                        'customer_id' => $customer?->id,
                    ]);
                    return;
                }
                
                Log::debug('Session resolved', [
                    'event_id' => $eventId,
                    'session_id' => $session->id,
                ]);

                // 4. Create event if it doesn't exist yet (now that we have session_id)
                if (!$eventId) {
                    $occurredAt = isset($eventData['timestamp']) 
                        ? Carbon::parse($eventData['timestamp']) 
                        : now();
                    
                    $eventId = DB::table('events')->insertGetId([
                        'website_id' => $website->id,
                        'session_id' => $session->id, // Required
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
                        'referrer_domain_id' => $referrerDomain,
                        'landing_page_id' => $landingPage,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    Log::debug('Event created', ['event_id' => $eventId]);
                }
                
                // Get the event
                $event = DB::table('events')->find($eventId);
                if (!$event) {
                    Log::error('Event not found after creation', ['event_id' => $eventId]);
                    return;
                }

                // 5. Link custom UTM values to event
                Log::debug('Step 5: Linking UTM values to event', ['event_id' => $eventId]);
                $this->linkCustomUtmValuesToEvent($eventId, $customUtmValues);

                // 6. Update event with any additional resolved data
                if ($customer && !$event->customer_id) {
                    DB::table('events')
                        ->where('id', $eventId)
                        ->update(['customer_id' => $customer->id, 'updated_at' => now()]);
                }

                // 7. Create or update touch if this is a new acquisition touchpoint
                Log::debug('Step 7: Resolving touch', ['event_id' => $eventId]);
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
                    'event_id' => $eventId,
                    'touch_id' => $touch?->id ?? null,
                ]);

                // 8. Handle conversion attribution if this is a conversion event
                if ($this->isConversionEvent($eventData['event'])) {
                    Log::debug('Step 8: Creating conversion', ['event_id' => $eventId]);
                    $this->createConversion(
                        website: $website,
                        customer: $customer,
                        session: $session,
                        event: $event,
                        eventData: $eventData,
                    );
                    Log::debug('Conversion created', ['event_id' => $eventId]);
                }

                Log::debug('Async event processing completed successfully', [
                    'event_id' => $eventId,
                    'customer_id' => $customer?->id,
                    'session_id' => $session?->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Error during async event processing', [
                    'event_id' => $eventId,
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

    // updateEvent method removed - event is now created with all required data upfront

    /**
     * Resolve customer from identity data.
     * 
     * @param string $ip Client IP address
     * @param string|null $userAgent Client User-Agent string
     */
    protected function resolveCustomer(Website $website, array $eventData, string $ip, ?string $userAgent): ?object
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
            // No identity provided - return null (don't create customer without reliable identity)
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
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $identity = (object) ['id' => $identityId];
        } else {
            DB::table('identities')
                ->where('id', $identity->id)
                ->update(['updated_at' => now()]);
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
                // If this is an email_hash identity, update customer's email_hash field
                if ($identityType === 'email_hash' && !$customer->email_hash) {
                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update(['email_hash' => $identityValueHash, 'updated_at' => now()]);
                }
                
                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['updated_at' => now()]);
                return $customer;
            }
        }

        // If this is an email_hash identity, try to find customer by email_hash field
        // This handles cases where email_hash appears in events but customer wasn't linked
        if ($identityType === 'email_hash') {
            $customerByEmail = $this->findCustomerByEmailHash($website, $identityValueHash);
            if ($customerByEmail) {
                // Link this email_hash identity to the existing customer
                DB::table('customer_identity_links')->insert([
                    'customer_id' => $customerByEmail->id,
                    'identity_id' => $identity->id,
                    'confidence' => 0.95, // High confidence for email_hash
                    'source' => 'heuristic',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                DB::table('customers')
                    ->where('id', $customerByEmail->id)
                    ->update(['updated_at' => now()]);
                
                return $customerByEmail;
            }
        }

        // Before creating a new customer, try to link to existing customer by IP
        // This handles cases where cookies are cleared (incognito mode, etc.)
        $existingCustomer = $this->findExistingCustomerByIp($website, $ip, $identityType);
        
        if ($existingCustomer) {
            // Link new identity to existing customer (identity stitching)
            DB::table('customer_identity_links')->insert([
                'customer_id' => $existingCustomer->id,
                'identity_id' => $identity->id,
                'confidence' => 0.7, // Lower confidence for IP-based linking
                'source' => 'heuristic',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // If this is an email_hash identity, update customer's email_hash field
            if ($identityType === 'email_hash' && !$existingCustomer->email_hash) {
                DB::table('customers')
                    ->where('id', $existingCustomer->id)
                    ->update(['email_hash' => $identityValueHash, 'updated_at' => now()]);
            }
            
            DB::table('customers')
                ->where('id', $existingCustomer->id)
                ->update(['updated_at' => now()]);
            
            return $existingCustomer;
        }

        // Create new customer
        $customerData = [
            'website_id' => $website->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // If this is an email_hash identity, store it in customer's email_hash field
        if ($identityType === 'email_hash') {
            $customerData['email_hash'] = $identityValueHash;
        }

        $customerId = DB::table('customers')->insertGetId($customerData);

        // Link identity to customer
        $confidence = match($identityType) {
            'user_id' => 1.0,
            'email_hash' => 0.95,
            'cookie' => 1.0,
            default => 0.9,
        };

        $source = match($identityType) {
            'user_id' => 'login',
            'email_hash' => 'login',
            default => 'sdk',
        };

        DB::table('customer_identity_links')->insert([
            'customer_id' => $customerId,
            'identity_id' => $identity->id,
            'confidence' => $confidence,
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('customers')->find($customerId);
    }

    /**
     * Find existing customer by IP address within a recent time window.
     * This helps link new cookie identities to existing customers when cookies are cleared.
     * 
     * @param string $ip Client IP address
     * @param string $identityType Type of identity being created (to exclude from matching)
     * @return object|null Existing customer or null
     */
    protected function findExistingCustomerByIp(Website $website, string $ip, string $identityType): ?object
    {
        // Only link cookie identities (not user_id or email_hash which are more reliable)
        if ($identityType !== 'cookie') {
            return null;
        }

        // Look for recent sessions (within last 2 hours) with the same IP
        // This handles cases where cookies are cleared but user is still on same device/IP
        $recentSession = DB::table('tracking_sessions')
            ->where('website_id', $website->id)
            ->where('ip', $ip)
            ->where('started_at', '>', now()->subHours(2))
            ->orderBy('started_at', 'desc')
            ->first();

        if (!$recentSession) {
            return null;
        }

        // Get the customer from the recent session
        $customer = DB::table('customers')
            ->where('id', $recentSession->customer_id)
            ->where('website_id', $website->id)
            ->first();

        // Only return if this customer doesn't have a recent cookie identity
        // This handles cases where cookies are cleared - we link new cookie to existing customer
        if ($customer) {
            // Check if customer has a recent cookie identity (within last 30 minutes)
            $hasRecentCookieIdentity = DB::table('customer_identity_links')
                ->join('identities', 'customer_identity_links.identity_id', '=', 'identities.id')
                ->where('customer_identity_links.customer_id', $customer->id)
                ->where('identities.type', 'cookie')
                ->where('identities.updated_at', '>', now()->subMinutes(30))
                ->exists();

            // If no recent cookie identity, link the new cookie to this customer
            // This handles cookie clearing (incognito mode, browser reset, etc.)
            if (!$hasRecentCookieIdentity) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Find existing customer by email hash.
     * This handles cases where email_hash appears in events but customer wasn't linked yet.
     * 
     * @param string $emailHash Hashed email address
     * @return object|null Existing customer or null
     */
    protected function findCustomerByEmailHash(Website $website, string $emailHash): ?object
    {
        // First check customers table directly
        $customer = DB::table('customers')
            ->where('website_id', $website->id)
            ->where('email_hash', $emailHash)
            ->first();

        if ($customer) {
            return $customer;
        }

        // Also check if there's an identity with this email_hash linked to a customer
        $identity = DB::table('identities')
            ->where('website_id', $website->id)
            ->where('type', 'email_hash')
            ->where('value_hash', $emailHash)
            ->first();

        if ($identity) {
            $link = DB::table('customer_identity_links')
                ->where('identity_id', $identity->id)
                ->first();

            if ($link) {
                $customer = DB::table('customers')
                    ->where('id', $link->customer_id)
                    ->where('website_id', $website->id)
                    ->first();

                if ($customer) {
                    // Update customer's email_hash field for faster future lookups
                    if (!$customer->email_hash) {
                        DB::table('customers')
                            ->where('id', $customer->id)
                            ->update(['email_hash' => $emailHash, 'updated_at' => now()]);
                    }
                    return $customer;
                }
            }
        }

        return null;
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
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $customUtmParam = (object) ['id' => $customUtmParamId];
                }

                // Find or create custom UTM value
                $customUtmValue = DB::table('custom_utm_values')
                    ->where('custom_utm_parameter_id', $customUtmParam->id)
                    ->where('value', $value)
                    ->first();

                if (!$customUtmValue) {
                    $customUtmValueId = DB::table('custom_utm_values')->insertGetId([
                        'custom_utm_parameter_id' => $customUtmParam->id,
                        'value' => $value,
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

        $landingPage = DB::table('landing_pages')
            ->where('website_id', $website->id)
            ->where('path', $path)
            ->first();

        if (!$landingPage) {
            return DB::table('landing_pages')->insertGetId([
                'website_id' => $website->id,
                'path' => $path,
                'full_url_sample' => strlen($url) > 500 ? substr($url, 0, 500) : $url,
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
            $session = DB::table('tracking_sessions')
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
        $activeSession = DB::table('tracking_sessions')
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
        $sessionId = DB::table('tracking_sessions')->insertGetId([
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

        return DB::table('tracking_sessions')->find($sessionId);
    }

    /**
     * Check if session should break due to campaign change.
     */
    protected function shouldBreakSession(object $session): bool
    {
        // Note: UTM parameters are not stored in tracking_sessions table
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
     * Create conversion with attribution.
     * 
     * Attribution priority:
     * 1. Current UTM (from URL) - highest priority
     * 2. Last Touch UTM (from cookies/customer) - fallback
     * 3. First Touch UTM (from cookies/customer) - final fallback
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

        // Extract current UTMs from event data (if present in URL)
        $utmCurrent = $this->extractCurrentUtms($eventData);

        // Get touches for attribution calculation
        // Note: We don't store first/last touch UTMs as JSON - they can be queried from touches
        $firstTouch = null;
        if ($customer->first_touch_id) {
        $firstTouch = DB::table('touches')
                ->where('id', $customer->first_touch_id)
            ->where('website_id', $website->id)
            ->first();
        }

        $lastTouch = null;
        if ($customer->last_touch_id) {
            $lastTouch = DB::table('touches')
                ->where('id', $customer->last_touch_id)
                ->where('website_id', $website->id)
                ->first();
        }

        // Calculate attribution UTMs based on priority:
        // 1. Current UTM (if present) - from purchase URL
        // 2. Last Touch UTM (if no current) - query from touch
        // 3. First Touch UTM (if no current or last) - query from touch
        $utmAttribution = null;
        if ($utmCurrent) {
            $utmAttribution = $utmCurrent;
        } elseif ($lastTouch) {
            $utmAttribution = $this->getUtmsFromTouch($lastTouch->id);
        } elseif ($firstTouch) {
            $utmAttribution = $this->getUtmsFromTouch($firstTouch->id);
        }

        // Extract order information from event properties
        $properties = $eventData['properties'] ?? [];
        $orderId = isset($properties['order_id']) ? (int) $properties['order_id'] : null;
        $orderNumber = $properties['order_number'] ?? $properties['order_key'] ?? null;

        // Also find last non-direct touch for compatibility with existing attribution model
        $lastNonDirectTouch = DB::table('touches')
            ->where('website_id', $website->id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('referrer_domain_id')
            ->orderBy('occurred_at', 'desc')
            ->first();

        // Determine attributed touch for backward compatibility
        // If current UTMs exist, try to find a touch from the current session
        // Otherwise, use last touch or first touch
        $attributedTouch = null;
        if ($utmCurrent && $session) {
            // Try to find a touch from the current session
            $currentSessionTouch = DB::table('touches')
                ->where('session_id', $session->id)
                ->where('website_id', $website->id)
                ->first();
            $attributedTouch = $currentSessionTouch ?? $lastTouch ?? $firstTouch;
        } else {
            $attributedTouch = $lastTouch ?? $firstTouch;
        }

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
            'utm_current' => $utmCurrent ? json_encode($utmCurrent) : null,
            'utm_attribution' => $utmAttribution ? json_encode($utmAttribution) : null,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Check if event is a conversion event.
     * 
     * Only tracks actual completed orders/purchases as conversions.
     * Note: checkout_completed is intentionally excluded to avoid duplicate
     * conversions, as it may fire before payment confirmation.
     */
    protected function isConversionEvent(string $eventName): bool
    {
        $conversionEvents = ['purchase', 'order', 'conversion'];
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
     * Link custom UTM values to session via polymorphic table.
     */
    protected function linkCustomUtmValuesToSession(int $sessionId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('trackable_utm_values')
                ->where('trackable_type', 'session')
                ->where('trackable_id', $sessionId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('trackable_utm_values')->insert([
                    'trackable_type' => 'session',
                    'trackable_id' => $sessionId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Link custom UTM values to event via polymorphic table.
     */
    protected function linkCustomUtmValuesToEvent(int $eventId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('trackable_utm_values')
                ->where('trackable_type', 'event')
                ->where('trackable_id', $eventId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('trackable_utm_values')->insert([
                    'trackable_type' => 'event',
                    'trackable_id' => $eventId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Link custom UTM values to touch via polymorphic table.
     */
    protected function linkCustomUtmValuesToTouch(int $touchId, array $customUtmValueIds): void
    {
        if (empty($customUtmValueIds)) {
            return;
        }

        foreach ($customUtmValueIds as $customUtmValueId) {
            // Check if link already exists
            $existing = DB::table('trackable_utm_values')
                ->where('trackable_type', 'touch')
                ->where('trackable_id', $touchId)
                ->where('custom_utm_value_id', $customUtmValueId)
                ->first();

            if (!$existing) {
                DB::table('trackable_utm_values')->insert([
                    'trackable_type' => 'touch',
                    'trackable_id' => $touchId,
                    'custom_utm_value_id' => $customUtmValueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Extract current UTMs from event data (from URL).
     * Checks both top-level event data and parses URL for UTM parameters.
     * 
     * @return array<string, string>|null Associative array of UTM parameters (e.g., ['utm_source' => 'google', 'utm_campaign' => 'summer'])
     */
    protected function extractCurrentUtms(array $eventData): ?array
    {
        $utms = [];
        
        // 1. Check top-level event data for UTM parameters
        foreach ($eventData as $key => $value) {
            if (str_starts_with($key, 'utm_') && is_string($value) && !empty($value)) {
                $utms[$key] = $value;
            }
        }
        
        // 2. If no UTMs found in top-level data, try parsing from URL
        if (empty($utms) && !empty($eventData['url'])) {
            $url = $eventData['url'];
            $parsedUrl = parse_url($url);
            
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                
                foreach ($queryParams as $key => $value) {
                    if (str_starts_with($key, 'utm_') && is_string($value) && !empty($value)) {
                        $utms[$key] = $value;
                    }
                }
            }
        }
        
        return !empty($utms) ? $utms : null;
    }

    /**
     * Get UTMs from a touch by querying through the junction table.
     * 
     * @param int $touchId The touch ID
     * @return array<string, string>|null Associative array of UTM parameters
     */
    protected function getUtmsFromTouch(int $touchId): ?array
    {
        $utms = [];
        
        // Get all custom UTM values linked to this touch via polymorphic table
        $touchUtmValues = DB::table('trackable_utm_values')
            ->where('trackable_type', 'touch')
            ->where('trackable_id', $touchId)
            ->join('custom_utm_values', 'trackable_utm_values.custom_utm_value_id', '=', 'custom_utm_values.id')
            ->join('custom_utm_parameters', 'custom_utm_values.custom_utm_parameter_id', '=', 'custom_utm_parameters.id')
            ->select(
                'custom_utm_parameters.name as param_name',
                'custom_utm_values.value as param_value'
            )
            ->get();
        
        foreach ($touchUtmValues as $touchUtmValue) {
            // Reconstruct the UTM parameter name with 'utm_' prefix
            $utmKey = 'utm_' . $touchUtmValue->param_name;
            $utms[$utmKey] = $touchUtmValue->param_value;
        }
        
        return !empty($utms) ? $utms : null;
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
