<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

final class GenerationResult
{
    private bool $successful;

    private ?string $text;

    private ?string $providerId;

    private ?string $modelId;

    private ?string $errorCode;

    private ?string $errorMessage;

    private function __construct(
        bool $successful,
        ?string $text = null,
        ?string $providerId = null,
        ?string $modelId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ) {
        $this->successful = $successful;
        $this->text = $text;
        $this->providerId = $providerId;
        $this->modelId = $modelId;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function success(
        string $text,
        ?string $providerId = null,
        ?string $modelId = null
    ): self {
        return new self(
            true,
            $text,
            $providerId,
            $modelId
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage
    ): self {
        return new self(
            false,
            null,
            null,
            null,
            $errorCode,
            $errorMessage
        );
    }

    public function isSuccess(): bool
    {
        return $this->successful;
    }

    public function isFailure(): bool
    {
        return ! $this->successful;
    }

    public function text(): ?string
    {
        return $this->text;
    }

    public function providerId(): ?string
    {
        return $this->providerId;
    }

    public function modelId(): ?string
    {
        return $this->modelId;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }
}