<?php

declare(strict_types=1);

namespace DetIt\AI;

final class OutputSchema
{
    public const FIELD_TITLE = 'title';
    public const FIELD_SHORT_DESCRIPTION = 'short_description';
    public const FIELD_DESCRIPTION = 'description';
    public const FIELD_META_DESCRIPTION = 'meta_description';
    public const FIELD_TAGS = 'tags';

    /**
     * @return array<int, string>
     */
    public static function fields(): array
    {
        return [
            self::FIELD_TITLE,
            self::FIELD_SHORT_DESCRIPTION,
            self::FIELD_DESCRIPTION,
            self::FIELD_META_DESCRIPTION,
            self::FIELD_TAGS,
        ];
    }

    /**
     * Return the JSON schema for generated product content.
     *
     * @param array<int, string>|null $selectedFields
     * @return array<string, mixed>
     */
    public static function definition(?array $selectedFields = null): array
    {
        $fields = self::normalizeFields(
            $selectedFields ?? self::fields()
        );

        $properties = [];

        foreach ($fields as $field) {
            $properties[$field] = self::propertyDefinition($field);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $fields,
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<int, string> $fields
     * @return array<int, string>
     */
    private static function normalizeFields(array $fields): array
    {
        $allowedFields = self::fields();

        $normalized = array_values(
            array_unique(
                array_filter(
                    $fields,
                    static fn ($field): bool =>
                        is_string($field)
                        && in_array($field, $allowedFields, true)
                )
            )
        );

        /*
         * Preserve canonical field order regardless of the order
         * provided by the caller.
         */
        return array_values(
            array_filter(
                $allowedFields,
                static fn (string $field): bool =>
                    in_array($field, $normalized, true)
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function propertyDefinition(string $field): array
    {
        if ($field === self::FIELD_TAGS) {
            return [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ];
        }

        return [
            'type' => 'string',
        ];
    }
}