<?php

declare(strict_types=1);

namespace DetIt\AI;

final class GenerationOutcome
{
    private ?GenerationResult $result;
    private ?string $providerId;
    private ?string $modelId;
    private ?string $errorCode;
    private ?string $errorMessage;

    private function __construct(
        ?GenerationResult $result = null,
        ?string $providerId = null,
        ?string $modelId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ) {
        $this->result       = $result;
        $this->providerId   = $providerId;
        $this->modelId      = $modelId;
        $this->errorCode    = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    public static function success(
        GenerationResult $result,
        ?string $providerId = null,
        ?string $modelId = null
    ): self {
        return new self(
            $result,
            $providerId,
            $modelId
        );
    }

    public static function failure(
        string $errorCode,
        string $errorMessage
    ): self {
        return new self(
            null,
            null,
            null,
            $errorCode,
            $errorMessage
        );
    }

    public function isSuccess(): bool
    {
        return $this->result !== null;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    public function result(): ?GenerationResult
    {
        return $this->result;
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