<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoopsService
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.loops.api_key');
    }

    public function createContact(array $data)
    {
        // Skip if no API key is configured
        if (!$this->apiKey) {
            Log::info('Skipping Loops contact creation - no API key configured', [
                'email' => $data['email'] ?? 'unknown'
            ]);
            return ['success' => true, 'message' => 'Skipped - no API key'];
        }

        try {
            Log::info('Creating Loops contact', [
                'email' => $data['email'] ?? 'unknown',
                'data_keys' => array_keys($data)
            ]);

            $baseUrl = "https://app.loops.so/api/v1/contacts/create";
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl, $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Loops contact created successfully', [
                    'email' => $data['email'] ?? 'unknown',
                    'response' => $result
                ]);
                
                return $result;
            }

            Log::error('Loops API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $data['email'] ?? 'unknown',
                'sent_data' => $data
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status()
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Loops API connection error', [
                'message' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown',
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'status' => 0
            ];

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Loops API request exception', [
                'message' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown',
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => 'Request failed: ' . $e->getMessage(),
                'status' => $e->response ? $e->response->status() : 0
            ];

        } catch (\Exception $e) {
            Log::error('Loops API unexpected error', [
                'message' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }
}