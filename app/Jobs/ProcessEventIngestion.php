<?php

namespace App\Jobs;

use App\Http\Services\TrackingIngestionService;
use App\Models\IngestionToken;
use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEventIngestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $eventId,
        public int $websiteId,
        public int $ingestionTokenId,
        public array $eventData,
        public string $ip,
        public ?string $userAgent
    ) {
    }

    /**
     * Execute the job.
     * Handles everything: event storage + processing.
     */
    public function handle(TrackingIngestionService $service): void
    {
        Log::info('Processing event ingestion job', [
            'event_id' => $this->eventId,
            'website_id' => $this->websiteId,
            'event' => $this->eventData['event'] ?? null,
        ]);

        try {
            $website = Website::findOrFail($this->websiteId);
            $ingestionToken = IngestionToken::findOrFail($this->ingestionTokenId);

            // Process the event - creates event if eventId is null, then does all processing
            $service->processEventAsync(
                eventId: $this->eventId,
                website: $website,
                ingestionToken: $ingestionToken,
                eventData: $this->eventData,
                ip: $this->ip,
                userAgent: $this->userAgent
            );

            Log::info('Event processing job completed', [
                'event_id' => $this->eventId,
            ]);
        } catch (\Exception $e) {
            Log::error('Event processing job failed', [
                'event_id' => $this->eventId,
                'website_id' => $this->websiteId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Event processing job permanently failed', [
            'event_id' => $this->eventId,
            'website_id' => $this->websiteId,
            'error' => $exception->getMessage(),
            'event_data' => $this->eventData,
        ]);

        // Optionally, you could mark the event as failed or send a notification
        // For now, we'll just log it
    }
}
