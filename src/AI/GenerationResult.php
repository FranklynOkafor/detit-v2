<?php

namespace DetIt\AI;

use InvalidArgumentException;

if (! defined('ABSPATH')) {
    exit;
}

final class GenerationResult
{
    /**
     * @var string[]
     */
    private array $fields;

    private ?string $title;

    private ?string $shortDescription;

    private ?string $description;

    private ?string $metaDescription;

    /**
     * @var string[]|null
     */
    private ?array $tags;

    private function __construct(
        array $fields,
        ?string $title = null,
        ?string $shortDescription = null,
        ?string $description = null,
        ?string $metaDescription = null,
        ?array $tags = null
    ) {
        $this->fields = $fields;
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->description = $description;
        $this->metaDescription = $metaDescription;
        $this->tags = $tags;
    }

    /**
     * Create a GenerationResult from structured AI output.
     *
     * @param array<string, mixed> $data
     * @param string[]|null        $expectedFields
     */
    public static function fromArray(
        array $data,
        ?array $expectedFields = null
    ): self {
        $expectedFields = self::normalizeExpectedFields($expectedFields);

        self::assertExpectedKeys($data, $expectedFields);

        $title = null;
        $shortDescription = null;
        $description = null;
        $metaDescription = null;
        $tags = null;

        foreach ($expectedFields as $field) {
            switch ($field) {
                case OutputSchema::FIELD_TITLE:
                    $title = self::normalizeString(
                        $data[$field],
                        $field
                    );
                    break;

                case OutputSchema::FIELD_SHORT_DESCRIPTION:
                    $shortDescription = self::normalizeString(
                        $data[$field],
                        $field
                    );
                    break;

                case OutputSchema::FIELD_DESCRIPTION:
                    $description = self::normalizeString(
                        $data[$field],
                        $field
                    );
                    break;

                case OutputSchema::FIELD_META_DESCRIPTION:
                    $metaDescription = self::normalizeString(
                        $data[$field],
                        $field
                    );
                    break;

                case OutputSchema::FIELD_TAGS:
                    $tags = self::normalizeTags(
                        $data[$field]
                    );
                    break;
            }
        }

        return new self(
            $expectedFields,
            $title,
            $shortDescription,
            $description,
            $metaDescription,
            $tags
        );
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function shortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    /**
     * @return string[]|null
     */
    public function tags(): ?array
    {
        return $this->tags;
    }

    /**
     * @return string[]
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    /**
     * Convert the result back to an associative array.
     *
     * Only fields that were requested are returned.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->fields as $field) {
            switch ($field) {
                case OutputSchema::FIELD_TITLE:
                    $result[$field] = $this->title;
                    break;

                case OutputSchema::FIELD_SHORT_DESCRIPTION:
                    $result[$field] = $this->shortDescription;
                    break;

                case OutputSchema::FIELD_DESCRIPTION:
                    $result[$field] = $this->description;
                    break;

                case OutputSchema::FIELD_META_DESCRIPTION:
                    $result[$field] = $this->metaDescription;
                    break;

                case OutputSchema::FIELD_TAGS:
                    $result[$field] = $this->tags;
                    break;
            }
        }

        return $result;
    }

    /**
     * @param string[]|null $expectedFields
     *
     * @return string[]
     */
    private static function normalizeExpectedFields(
        ?array $expectedFields
    ): array {
        $allowedFields = OutputSchema::fields();

        if ($expectedFields === null) {
            return $allowedFields;
        }

        $normalized = [];

        foreach ($expectedFields as $field) {
            if (! is_string($field)) {
                throw new InvalidArgumentException(
                    'Expected field names must be strings.'
                );
            }

            $field = trim($field);

            if (! in_array($field, $allowedFields, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Unsupported generation field "%s".',
                        $field
                    )
                );
            }

            if (! in_array($field, $normalized, true)) {
                $normalized[] = $field;
            }
        }

        if ($normalized === []) {
            throw new InvalidArgumentException(
                'At least one generation field is required.'
            );
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @param string[]             $expectedFields
     */
    private static function assertExpectedKeys(
        array $data,
        array $expectedFields
    ): void {
        foreach ($expectedFields as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Missing required generation field "%s".',
                        $field
                    )
                );
            }
        }

        foreach (array_keys($data) as $field) {
            if (
                ! is_string($field) ||
                ! in_array($field, $expectedFields, true)
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Unexpected generation field "%s".',
                        (string) $field
                    )
                );
            }
        }
    }

    private static function normalizeString(
        mixed $value,
        string $field
    ): string {
        if (! is_string($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Generation field "%s" must be a string.',
                    $field
                )
            );
        }

        return trim($value);
    }

    /**
     * @return string[]
     */
    private static function normalizeTags(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(
                'Generation field "tags" must be an array.'
            );
        }

        $tags = [];

        foreach ($value as $index => $tag) {
            if (! is_string($tag)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Generation tag at index %s must be a string.',
                        (string) $index
                    )
                );
            }

            $tag = trim($tag);

            if ($tag === '') {
                continue;
            }

            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }
}