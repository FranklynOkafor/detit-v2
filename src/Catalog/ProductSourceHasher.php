<?php

declare(strict_types=1);

namespace DetIt\Catalog;

use DetIt\WooCommerce\ProductContext;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stage 59: SHA-256 fingerprint of merchant-controlled catalog source data.
 *
 * A hash only signals changes; it does not prove the data is correct.
 * It excludes volatile scan timestamps, generated scores and database rows.
 * Taxonomy/attribute list ordering is normalized where it has no meaning.
 */
final class ProductSourceHasher
{
    public const SOURCE_VERSION = 1;

    public function hash(ProductContext $product): string
    {
        $payload = $this->sourceData($product);

        $json = wp_json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (! is_string($json)) {
            throw new \RuntimeException('Unable to encode catalog source data for hashing.');
        }

        return hash('sha256', $json);
    }

    /**
     * Explicitly enumerate inputs so the hash is stable across PHP object
     * changes and future additions to ProductContext::toArray().
     *
     * @return array<string, mixed>
     */
    private function sourceData(ProductContext $product): array
    {
        return [
            'source_version' => self::SOURCE_VERSION,
            'product_id' => $product->id(),
            'title' => $product->title(),
            'sku' => $product->sku(),
            'type' => $product->type(),
            'description' => $product->description(),
            'short_description' => $product->shortDescription(),
            'categories' => $this->sortSet($product->categories()),
            'tags' => $this->sortSet($product->tags()),
            'attributes' => $this->sortSet($product->attributes()),
            'weight' => $product->weight(),
            'dimensions' => $this->sortKeys($product->dimensions()),
            'featured_image' => $this->sortKeys($product->featuredImage()),
            // Gallery order is intentionally significant to storefront display.
            'gallery_images' => array_map(
                fn (array $image): array => $this->sortKeys($image),
                $product->galleryImages()
            ),
        ];
    }

    /**
     * A collection of category, tag or attribute records is a set for audit
     * purposes. Sort records to avoid triggering scans when term order changes.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortSet(array $items): array
    {
        $normalized = array_map(
            fn (array $item): array => $this->sortKeys($item),
            array_values($items)
        );

        usort(
            $normalized,
            static fn (array $a, array $b): int =>
                strcmp(
                    (string) wp_json_encode($a),
                    (string) wp_json_encode($b)
                )
        );

        return $normalized;
    }

    /**
     * Sort associative keys recursively, preserving semantic array order
     * (e.g. attribute options and gallery positions).
     *
     * @param array<string|int, mixed>|null $value
     * @return array<string|int, mixed>|null
     */
    private function sortKeys(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $part) {
            if (is_array($part)) {
                $value[$key] = $this->sortKeys($part);
            }
        }

        return $value;
    }
}
