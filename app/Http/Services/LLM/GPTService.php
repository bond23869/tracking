<?php

namespace App\Http\Services\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;
use App\Interfaces\LLMServiceInterface;

class GPTService implements LLMServiceInterface
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;
    protected array $defaultOptions;
    protected float $temperature = 0.2;
    protected int $maxTokens = 2048;
    protected array $systemMessage = [];
    protected array $functions = [];
    protected ?string $functionCall = null;
    protected bool $forceJson = false;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured. Please check your .env file for OPENAI_API_KEY.');
        }
        
        $this->model = config('services.openai.default_model', 'gpt-4o-mini');
        $this->defaultOptions = [
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout'  => config('services.openai.timeout', 30.0),
        ]);
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        if ($temperature < 0 || $temperature > 2) {
            throw new InvalidArgumentException('Temperature must be between 0 and 2');
        }
        $this->temperature = $temperature;
        return $this;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withSystemMessage(string $message): self
    {
        $this->systemMessage = [
            'role' => 'system',
            'content' => $message
        ];
        return $this;
    }

    public function withFunctions(array $functions, ?string $functionCall = null): self
    {
        $this->functions = $functions;
        $this->functionCall = $functionCall;
        return $this;
    }

    public function forceJsonResponse(bool $force = true): self
    {
        $this->forceJson = $force;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Check if the model is a newer GPT model that requires max_completion_tokens instead of max_tokens
     */
    protected function isNewerGptModel(string $model): bool
    {
        // GPT-5 and newer models require max_completion_tokens
        // Also handle models with date suffixes (e.g., gpt-5-nano-2025-08-07)
        return preg_match('/^gpt-[5-9]/', $model) || 
               preg_match('/^gpt-[4-9].*-20(2[5-9]|[3-9]\d)/', $model) ||
               str_contains($model, 'gpt-5');
    }

    public function generate(string|array $prompt): array
    {
        $messages = [];
        $jsonInstructionAdded = false;

        if (!empty($this->systemMessage)) {
            if ($this->forceJson) {
                $this->systemMessage['content'] .= "\n\nYou MUST respond in JSON format.";
                $jsonInstructionAdded = true;
            }
            $messages[] = $this->systemMessage;
        }

        if (is_string($prompt)) {
            $userPrompt = ['role' => 'user', 'content' => $prompt];
            if ($this->forceJson && !$jsonInstructionAdded) {
                $userPrompt['content'] .= "\n\nYou MUST respond in JSON format.";
                $jsonInstructionAdded = true;
            }
            $messages[] = $userPrompt;
        } elseif (is_array($prompt)) {
            if ($this->forceJson && !$jsonInstructionAdded) {
                array_unshift($messages, ['role' => 'system', 'content' => 'You MUST respond in JSON format.']);
                $jsonInstructionAdded = true;
            }
            $messages = array_merge($messages, $prompt);
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        // Newer GPT models have restrictions on parameters
        if ($this->isNewerGptModel($this->model)) {
            // Use max_completion_tokens and exclude temperature (uses default)
            $payload['max_completion_tokens'] = $this->maxTokens;
        } else {
            // Use legacy parameters for older models
            $payload['max_tokens'] = $this->maxTokens;
            $payload['temperature'] = $this->temperature;
        }

        if ($this->forceJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if (!empty($this->functions)) {
            $payload['functions'] = $this->functions;
            if ($this->functionCall) {
                $payload['function_call'] = $this->functionCall;
            }
        }

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody(), true);

            $this->forceJson = false;
            if ($jsonInstructionAdded && isset($this->systemMessage['content'])) {
                $this->systemMessage['content'] = str_replace("\n\nYou MUST respond in JSON format.", '', $this->systemMessage['content']);
            }

            // Normalize usage data to match Gemini format expected by Prompt class
            $normalizedUsage = null;
            if (isset($data['usage'])) {
                $usage = $data['usage'];
                $normalizedUsage = [
                    'promptTokenCount' => $usage['prompt_tokens'] ?? 0,
                    'candidatesTokenCount' => $usage['completion_tokens'] ?? 0,
                    'totalTokenCount' => $usage['total_tokens'] ?? 0,
                ];
            }

            return [
                'content' => $data['choices'][0]['message']['content'] ?? null,
                'function_call' => $data['choices'][0]['message']['function_call'] ?? null,
                'usage' => $normalizedUsage,
                'model' => $data['model'],
                'raw_response' => $data,
            ];
        } catch (GuzzleException $e) {
            $this->forceJson = false;
            if ($jsonInstructionAdded && isset($this->systemMessage['content'])) {
                $this->systemMessage['content'] = str_replace("\n\nYou MUST respond in JSON format.", '', $this->systemMessage['content']);
            }
            throw new RuntimeException("API request failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    public function generateEmbedding(string $text): array
    {
        try {
            $response = $this->client->post('embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['data'][0]['embedding'])) {
                throw new RuntimeException('Invalid response structure received from embedding API.');
            }

            // Normalize usage data to match Gemini format expected by Prompt class
            $normalizedUsage = null;
            if (isset($data['usage'])) {
                $usage = $data['usage'];
                $normalizedUsage = [
                    'promptTokenCount' => $usage['prompt_tokens'] ?? 0,
                    'candidatesTokenCount' => 0, // Embeddings don't have completion tokens
                    'totalTokenCount' => $usage['total_tokens'] ?? 0,
                ];
            }

            return [
                'embedding' => $data['data'][0]['embedding'],
                'usage' => $normalizedUsage,
            ];
        } catch (GuzzleException $e) {
            throw new RuntimeException("Embedding generation failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    public function streamGenerate(string $prompt, callable $callback): void
    {
        if ($this->forceJson) {
            throw new InvalidArgumentException('JSON response format cannot be used with streaming.');
        }
        // Implementation for streaming responses
        // This would use Server-Sent Events (SSE) to stream the response
        // ...
    }
}
