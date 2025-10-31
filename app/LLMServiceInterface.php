<?php

namespace App\Interfaces;
interface LLMServiceInterface
{
    public function generate(string|array $prompt): array;
    public function setModel(string $model): self;
    public function setTemperature(float $temperature): self;
    public function withSystemMessage(string $message): self;
    public function generateEmbedding(string $text): array;
}
