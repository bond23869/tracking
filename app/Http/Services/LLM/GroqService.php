<?php

namespace App\Http\Services\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;
use App\Interfaces\LLMServiceInterface;

class GroqService implements LLMServiceInterface
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
        $this->apiKey = config('services.groq.api_key');
        
        if (empty($this->apiKey)) {
            throw new RuntimeException('Groq API key is not configured. Please check your .env file for GROQ_API_KEY.');
        }
        
        $this->model = config('services.groq.default_model', 'openai/gpt-oss-20b');
        $this->defaultOptions = [
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        $this->client = new Client([
            'base_uri' => 'https://api.groq.com/openai/v1/',
            'timeout'  => config('services.groq.timeout', 30.0),
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
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        if ($this->forceJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if (!empty($this->functions)) {
            $payload['tools'] = array_map(function ($function) {
                return [
                    'type' => 'function',
                    'function' => $function
                ];
            }, $this->functions);
            
            if ($this->functionCall) {
                $payload['tool_choice'] = $this->functionCall;
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

            // Handle function calls in the new tools format
            $functionCall = null;
            if (isset($data['choices'][0]['message']['tool_calls']) && !empty($data['choices'][0]['message']['tool_calls'])) {
                $toolCall = $data['choices'][0]['message']['tool_calls'][0];
                if ($toolCall['type'] === 'function') {
                    $functionCall = [
                        'name' => $toolCall['function']['name'],
                        'arguments' => $toolCall['function']['arguments']
                    ];
                }
            }

            return [
                'content' => $data['choices'][0]['message']['content'] ?? null,
                'function_call' => $functionCall,
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
        // Note: Groq doesn't currently support embeddings through their API
        // This method is included for interface compliance but will throw an exception
        throw new RuntimeException('Groq does not currently support embedding generation. Please use OpenAI or another provider for embeddings.');
    }

    public function streamGenerate(string $prompt, callable $callback): void
    {
        if ($this->forceJson) {
            throw new InvalidArgumentException('JSON response format cannot be used with streaming.');
        }
        
        $messages = [];
        
        if (!empty($this->systemMessage)) {
            $messages[] = $this->systemMessage;
        }
        
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => true,
        ];

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->read(1024);
                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);
                    if (trim($jsonData) === '[DONE]') {
                        break;
                    }
                    
                    $data = json_decode(trim($jsonData), true);
                    if ($data && isset($data['choices'][0]['delta']['content'])) {
                        $callback($data['choices'][0]['delta']['content']);
                    }
                }
            }
        } catch (GuzzleException $e) {
            throw new RuntimeException("Streaming request failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
