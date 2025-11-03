<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TrackEventRequest;
use App\Http\Services\TrackingIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    public function __construct(
        protected TrackingIngestionService $trackingService
    ) {
    }

    /**
     * Handle tracking event ingestion.
     */
    public function track(TrackEventRequest $request): JsonResponse
    {
        try {
            $result = $this->trackingService->ingestEvent(
                website: $request->website,
                ingestionToken: $request->ingestion_token,
                eventData: $request->validated(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return response()->json([
                'success' => true,
                'event_id' => $result['event_id'],
                'customer_id' => $result['customer_id'],
                'session_id' => $result['session_id'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process event',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Health check endpoint for tracking API.
     */
    public function health(): JsonResponse
    {
        Log::info('Health check endpoint called');
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Validate API token endpoint.
     * Returns success if token is valid, or 401 if invalid (handled by middleware).
     */
    public function validateToken(): JsonResponse
    {
        $request = request();
        
        // If we reach here, the token is valid (middleware already verified it)
        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'website_id' => $request->website->id,
            'website_name' => $request->website->name,
            'token_name' => $request->ingestion_token->name,
        ]);
    }
}
