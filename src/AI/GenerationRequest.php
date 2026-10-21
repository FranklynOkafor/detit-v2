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

    private ?string $imageDataUri;

    public function __construct(
        string $prompt,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?string $systemInstruction = null,
        ?string $imageDataUri = null
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

        /*
         * Image input is optional.
         * Only allow supported image data URI formats.
         */
        if ($imageDataUri !== null) {
            $validImage = preg_match(
                '~^data:image/(?:jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$~D',
                $imageDataUri
            ) === 1;

            if (
                ! $validImage
                || strlen($imageDataUri) > 3 * 1024 * 1024
            ) {
                throw new \InvalidArgumentException(
                    'Invalid or oversized image data URI.'
                );
            }
        }

        $this->prompt = $prompt;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;
        $this->imageDataUri = $imageDataUri;

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

    public function imageDataUri(): ?string
    {
        return $this->imageDataUri;
    }
}
