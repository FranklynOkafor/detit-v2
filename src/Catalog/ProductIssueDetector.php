<?php

declare(strict_types=1);

namespace DetIt\Catalog;

use DetIt\WooCommerce\ProductContext;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Deterministic, read-only catalog diagnostics.
 *
 * - Uses only ProductContext data already read from WooCommerce.
 * - Never calls the AI provider or writes to a product.
 * - "info" flags are optional enrichment opportunities, not defects.
 * - Does not assign health scores; that belongs to Stage 58.
 */
final class ProductIssueDetector
{
    /**
     * Stable output, suitable for later health scoring and audit history.
     *
     * @return array<int, array{
     *     code:string,
     *     field:string,
     *     severity:string,
     *     message:string
     * }>
     */
    public function detect(ProductContext $product): array
    {
        $issues = [];

        if (! $this->hasVisibleText($product->title())) {
            $issues[] = $this->issue(
                'missing_title',
                'title',
                'high',
                'Product title is empty.'
            );
        }

        if (! $this->hasVisibleText($product->description())) {
            $issues[] = $this->issue(
                'missing_description',
                'description',
                'medium',
                'Product description has no visible text.'
            );
        }

        if (! $this->hasVisibleText($product->shortDescription())) {
            $issues[] = $this->issue(
                'missing_short_description',
                'short_description',
                'medium',
                'Short description has no visible text.'
            );
        }

        $image = $product->featuredImage();
        $hasImage = is_array($image)
            && (int) ($image['id'] ?? 0) > 0
            && trim((string) ($image['url'] ?? '')) !== '';

        if (! $hasImage) {
            $issues[] = $this->issue(
                'missing_featured_image',
                'featured_image',
                'high',
                'No usable featured image URL is available.'
            );
        } elseif (! $this->hasVisibleText((string) ($image['alt'] ?? ''))) {
            $issues[] = $this->issue(
                'missing_image_alt',
                'featured_image.alt',
                'info',
                'Featured image has no alternative text; review whether descriptive alt text is appropriate.'
            );
        }

        if ($product->categories() === []) {
            $issues[] = $this->issue(
                'missing_categories',
                'categories',
                'medium',
                'No product categories are assigned.'
            );
        }

        if ($product->tags() === []) {
            $issues[] = $this->issue(
                'missing_tags',
                'tags',
                'info',
                'No product tags are assigned (optional).'
            );
        }

        if (! $this->hasVisibleText($product->sku())) {
            $issues[] = $this->issue(
                'missing_sku',
                'sku',
                'info',
                'No SKU is recorded (optional).'
            );
        }

        if (! $this->hasUsableAttributes($product->attributes())) {
            $issues[] = $this->issue(
                'missing_attributes',
                'attributes',
                $product->type() === 'variable' ? 'medium' : 'info',
                $product->type() === 'variable'
                    ? 'Variable product has no populated attributes; review its variation configuration.'
                    : 'No populated product attributes are recorded (optional).'
            );
        }

        return $issues;
    }

    /**
     * A description containing only HTML tags, entities or whitespace
     * is not meaningful descriptive text. Treat invisible Unicode spacing
     * and zero-width characters as empty as well.
     */
    private function hasVisibleText(string $value): bool
    {
        $text = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $withoutSpace = preg_replace(
            '/[\s\x{00A0}\x{200B}\x{FEFF}]+/u',
            '',
            $text
        );

        return is_string($withoutSpace) && $withoutSpace !== '';
    }

    /**
     * A named attribute with zero options is not populated.
     *
     * @param array<int, array<string, mixed>> $attributes
     */
    private function hasUsableAttributes(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            $options = $attribute['options'] ?? [];

            if (! is_array($options)) {
                continue;
            }

            foreach ($options as $option) {
                if (
                    is_string($option)
                    && $this->hasVisibleText($option)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{code:string,field:string,severity:string,message:string} */
    private function issue(
        string $code,
        string $field,
        string $severity,
        string $message
    ): array {
        return compact('code', 'field', 'severity', 'message');
    }
}
