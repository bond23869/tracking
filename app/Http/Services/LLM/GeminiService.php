<?php

namespace App\Http\Services\LLM;

use App\Interfaces\LLMServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use App\Http\Services\LLM\GPTService;

class GeminiService implements LLMServiceInterface
{
    protected string $apiKey;
    protected string $model;
    protected array $defaultOptions;
    protected int $maxRetries = 5;
    protected int $retryDelay = 3; // seconds
    protected bool $forceJson = false;
    protected ?GPTService $fallbackService = null;
    protected Client $client;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.default_model', 'gemini-2.0-flash-lite');
        $this->defaultOptions = [
            'temperature' => 0.2,
            'max_tokens' => 2048,
        ];
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'timeout'  => config('services.google.timeout', 60.0),
            'connect_timeout' => 10.0,
        ]);

        // Initialize fallback service if OpenAI key is available
        if (config('services.openai.api_key')) {
            $this->fallbackService = new GPTService();
        }
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        $this->defaultOptions['temperature'] = $temperature;
        return $this;
    }

    public function withSystemMessage(string $message): self
    {
        $this->defaultOptions['system_message'] = $message;
        return $this;
    }

    public function forceJsonResponse(bool $force = true): self
    {
        $this->forceJson = $force;
        return $this;
    }

    public function generate(string|array $prompt): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                return $this->makeGenerateRequest($prompt);
            } catch (ConnectException $e) {
                $lastException = $e;
                $attempts++;
                
                // Log the retry attempt
                Log::warning('Gemini API connection failed, attempt ' . $attempts . ' of ' . $this->maxRetries, [
                    'error' => $e->getMessage(),
                    'curl_error_code' => $e->getHandlerContext()['errno'] ?? null,
                ]);

                if ($attempts < $this->maxRetries) {
                    sleep($this->retryDelay * $attempts); // Exponential backoff
                    continue;
                }
            } catch (RequestException $e) {
                // For other request exceptions, we might want to handle differently
                Log::error('Gemini API request failed', [
                    'error' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
                ]);
                
                // Try fallback service if available
                if ($this->fallbackService) {
                    Log::info('Attempting fallback to GPT service');
                    try {
                        return $this->fallbackService
                            ->setTemperature($this->defaultOptions['temperature'])
                            ->forceJsonResponse($this->forceJson)
                            ->generate($prompt);
                    } catch (\Exception $fallbackException) {
                        Log::error('Fallback to GPT service failed', [
                            'error' => $fallbackException->getMessage()
                        ]);
                    }
                }
                
                throw $e;
            }
        }

        // If we've exhausted all retries, try fallback service
        if ($this->fallbackService) {
            Log::info('Attempting fallback to GPT service after max retries');
            try {
                return $this->fallbackService
                    ->setTemperature($this->defaultOptions['temperature'])
                    ->forceJsonResponse($this->forceJson)
                    ->generate($prompt);
            } catch (\Exception $fallbackException) {
                Log::error('Fallback to GPT service failed', [
                    'error' => $fallbackException->getMessage()
                ]);
            }
        }

        // If everything fails, throw the last exception
        throw $lastException ?? new \RuntimeException('Failed to generate content after ' . $this->maxRetries . ' attempts');
    }

    protected function makeGenerateRequest(string|array $prompt): array
    {
        $response = $this->client->post('/v1beta/models/' . $this->model . ':generateContent', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => is_array($prompt) ? implode("\n", $prompt) : $prompt,
                            ]
                        ]
                    ]
                ],
            ],
        ]);

        $responseData = json_decode($response->getBody(), true);

        // Extract the text content
        $rawContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($rawContent === null) {
            return [
                'content' => null,
                'usage' => $responseData['usageMetadata'] ?? null,
            ];
        }

        // Extract JSON from markdown block if present
        $jsonContent = $rawContent;
        if (preg_match('/```json\n(.*)\n```/s', $rawContent, $matches)) {
            $jsonContent = $matches[1];
        }

        // Decode the JSON content
        $decodedContent = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'content' => $rawContent,
                'usage' => $responseData['usageMetadata'] ?? null,
            ];
        }

        return [
            'content' => $decodedContent,
            'usage' => $responseData['usageMetadata'] ?? null,
        ];
    }

    public function generateEmbedding(string $text): array
    {
        $response = $this->client->post('v1/models/' . $this->model . '/embedText', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'text' => $text,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
