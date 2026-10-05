<?php

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductWriter
{
    /**
     * Fields DetIt is currently allowed to write.
     *
     * @var array<int, string>
     */
    private const ALLOWED_FIELDS = [
        'title',
        'short_description',
        'description',
        'tags',
    ];

    /**
     * Write approved DetIt content to a WooCommerce product.
     *
     * Only fields explicitly supplied in $fields are changed.
     *
     * @param array<string, mixed> $fields
     */
    public function write(
        int $productId,
        array $fields
    ): bool {
        if ($productId <= 0) {
            return false;
        }

        if (! function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return false;
        }

        if (! $this->containsOnlyAllowedFields($fields)) {
            return false;
        }

        foreach ($fields as $field => $value) {
            switch ($field) {
                case 'title':
                    if (! is_string($value)) {
                        return false;
                    }

                    $product->set_name(
                        sanitize_text_field($value)
                    );
                    break;

                case 'short_description':
                    if (! is_string($value)) {
                        return false;
                    }

                    $product->set_short_description(
                        wp_kses_post($value)
                    );
                    break;

                case 'description':
                    if (! is_string($value)) {
                        return false;
                    }

                    $product->set_description(
                        wp_kses_post($value)
                    );
                    break;

                case 'tags':
                    if (! is_array($value)) {
                        return false;
                    }

                    $tagIds = $this->resolveTagIds(
                        $value
                    );

                    if ($tagIds === null) {
                        return false;
                    }

                    $product->set_tag_ids($tagIds);
                    break;
            }
        }

        $product->save();

        return true;
    }

    /**
     * Reject the complete operation if even one supplied
     * field is outside DetIt's explicit write allowlist.
     *
     * @param array<string, mixed> $fields
     */
    private function containsOnlyAllowedFields(
        array $fields
    ): bool {
        foreach (array_keys($fields) as $field) {
            if (
                ! is_string($field)
                || ! in_array(
                    $field,
                    self::ALLOWED_FIELDS,
                    true
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert generated tag names into WooCommerce
     * product-tag term IDs.
     *
     * Existing terms are reused. Missing terms are created.
     *
     * @param array<int, mixed> $tags
     * @return array<int, int>|null
     */
    private function resolveTagIds(
        array $tags
    ): ?array {
        $tagIds = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                return null;
            }

            $tag = sanitize_text_field($tag);

            if ($tag === '') {
                continue;
            }

            $existing = term_exists(
                $tag,
                'product_tag'
            );

            if ($existing === null) {
                $created = wp_insert_term(
                    $tag,
                    'product_tag'
                );

                if (is_wp_error($created)) {
                    return null;
                }

                $tagIds[] = (int) $created['term_id'];

                continue;
            }

            if (is_array($existing)) {
                $tagIds[] = (int) $existing['term_id'];
            } else {
                $tagIds[] = (int) $existing;
            }
        }

        return array_values(
            array_unique($tagIds)
        );
    }
}
