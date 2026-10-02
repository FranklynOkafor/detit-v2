<?php

declare(strict_types=1);

namespace DetIt\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductSnapshot
{
    private const ALLOWED_FIELDS = [
        'title',
        'short_description',
        'description',
        'tags',
    ];

    public function capture(
        int $productId,
        array $fields
    ): array|false {
        if (
            $productId <= 0
            || ! function_exists('wc_get_product')
        ) {
            return false;
        }

        $product = wc_get_product($productId);

        if (! $product) {
            return false;
        }

        $requestedFields = array_values(
            array_unique($fields)
        );

        if ($requestedFields === []) {
            return false;
        }

        foreach ($requestedFields as $field) {
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

        $snapshot = [];

        foreach ($requestedFields as $field) {
            switch ($field) {
                case 'title':
                    $snapshot[$field] =
                        $product->get_name('edit');
                    break;

                case 'short_description':
                    $snapshot[$field] =
                        $product->get_short_description('edit');
                    break;

                case 'description':
                    $snapshot[$field] =
                        $product->get_description('edit');
                    break;

                case 'tags':
                    $tags = wp_get_post_terms(
                        $productId,
                        'product_tag',
                        [
                            'fields' => 'names',
                        ]
                    );

                    if (is_wp_error($tags)) {
                        return false;
                    }

                    $snapshot[$field] =
                        array_values($tags);
                    break;
            }
        }

        return $snapshot;
    }
}
