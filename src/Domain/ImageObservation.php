<?php

declare(strict_types=1);

namespace DetIt\Domain;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An AI interpretation of an image, never an authoritative product fact.
 * The attachment ID lets later preview/review features show its provenance.
 */
final class ImageObservation
{
    private string $observation;

    public function __construct(
        private int $attachmentId,
        string $observation,
        private ?string $providerId = null,
        private ?string $modelId = null
    ) {
        $observation = trim($observation);

        if ($attachmentId <= 0) {
            throw new \InvalidArgumentException(
                'Image attachment ID must be positive.'
            );
        }

        if ($observation === '' || strlen($observation) > 4000) {
            throw new \InvalidArgumentException(
                'Image observation is empty or too long.'
            );
        }

        $this->observation = $observation;
    }

    public function observation(): string
    {
        return $this->observation;
    }

    public function attachmentId(): int
    {
        return $this->attachmentId;
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'source' => 'ai_image_observation',
            'verification_status' => 'unverified',
            'attachment_id' => $this->attachmentId,
            'observation' => $this->observation,
            'provider_id' => $this->providerId,
            'model_id' => $this->modelId,
        ];
    }
}
