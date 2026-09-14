<?php

namespace DetIt\AI;

if (! defined('ABSPATH')) {
    exit;
}

final class AIClientException extends \RuntimeException
{
    private AIErrorCode $errorCode;

    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        AIErrorCode $errorCode,
        string $message,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );

        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function errorCode(): AIErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}