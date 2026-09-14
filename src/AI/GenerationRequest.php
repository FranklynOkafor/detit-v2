<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

final class GenerationRequest
{
    private string $prompt;

    private ?float $temperature;

    private ?int $maxTokens;

    private ?string $systemInstruction;

    public function __construct(
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?string $systemInstruction = null
    ) {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new \InvalidArgumentException(
                'Generation prompt cannot be empty.'
            );
        }

        if ($maxTokens !== null && $maxTokens < 1) {
            throw new \InvalidArgumentException(
                'Maximum token count must be greater than zero.'
            );
        }

        $this->prompt = $prompt;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;

        $systemInstruction = $systemInstruction !== null
            ? trim($systemInstruction)
            : null;

        $this->systemInstruction = $systemInstruction !== ''
            ? $systemInstruction
            : null;
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function temperature(): ?float
    {
        return $this->temperature;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function systemInstruction(): ?string
    {
        return $this->systemInstruction;
    }
}