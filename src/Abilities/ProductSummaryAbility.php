<?php

declare(strict_types=1);

namespace DetIt\Abilities;

use DetIt\WooCommerce\ProductReader;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers DetIt's first WordPress Ability: a strictly read-only product
 * summary. No AI request, background job, or WooCommerce write occurs.
 */
final class ProductSummaryAbility
{
    public const NAME = 'detit/get-product-summary';

    private const CATEGORY = 'detit-catalog';

    private const TEXT_DOMAIN = 'detit-product-content-generator-for-woocommerce';

    public function registerHooks(): void
    {
        // DetIt still supports WordPress 6.5+, but Abilities require 6.9+.
        if (
            ! function_exists('wp_register_ability')
            || ! function_exists('wp_register_ability_category')
        ) {
            return;
        }

        add_action(
            'wp_abilities_api_categories_init',
            [$this, 'registerCategory']
        );

        add_action(
            'wp_abilities_api_init',
            [$this, 'registerAbility']
        );
    }

    public function registerCategory(): void
    {
        wp_register_ability_category(
            self::CATEGORY,
            [
                'label' => __(
                    'DetIt Catalog',
                    self::TEXT_DOMAIN
                ),
                'description' => __(
                    'Read-only catalog information provided by DetIt.',
                    self::TEXT_DOMAIN
                ),
            ]
        );
    }

    public function registerAbility(): void
    {
        wp_register_ability(
            self::NAME,
            [
                'label' => __(
                    'Get WooCommerce Product Summary',
                    self::TEXT_DOMAIN
                ),
                'description' => __(
                    'Return a read-only summary of a WooCommerce product. '
                    . 'Requires permission to manage WooCommerce and edit the product.',
                    self::TEXT_DOMAIN
                ),
                'category' => self::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'description' => 'WooCommerce product post ID.',
                        ],
                    ],
                    'required' => ['product_id'],
                    'additionalProperties' => false,
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'has_description' => ['type' => 'boolean'],
                        'has_short_description' => ['type' => 'boolean'],
                        'has_featured_image' => ['type' => 'boolean'],
                        'category_count' => ['type' => 'integer'],
                        'tag_count' => ['type' => 'integer'],
                        'attribute_count' => ['type' => 'integer'],
                    ],
                    'required' => [
                        'product_id',
                        'title',
                        'type',
                        'has_description',
                        'has_short_description',
                        'has_featured_image',
                        'category_count',
                        'tag_count',
                        'attribute_count',
                    ],
                    'additionalProperties' => false,
                ],
                'permission_callback' => [$this, 'canRead'],
                'execute_callback' => [$this, 'execute'],
                'meta' => [
                    'show_in_rest' => false,
                    'annotations' => [
                        'readonly' => true,
                        'destructive' => false,
                        'idempotent' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Enforce BOTH store-level permission and product-specific editing rights.
     *
     * @param array<string, mixed> $input Ability input after schema validation.
     */
    public function canRead(array $input): bool
    {
        if (
            ! is_user_logged_in()
            || ! current_user_can('manage_woocommerce')
        ) {
            return false;
        }

        $productId = $input['product_id'] ?? null;

        if (! is_int($productId) || $productId < 1) {
            return false;
        }

        $post = get_post($productId);

        if (
            ! $post instanceof \WP_Post
            || $post->post_type !== 'product'
        ) {
            return false;
        }

        return current_user_can('edit_post', $productId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, int|string|bool>|\WP_Error
     */
    public function execute(array $input): array|\WP_Error
    {
        // Defense in depth if another PHP caller invokes this directly.
        if (! $this->canRead($input)) {
            return new \WP_Error(
                'detit_ability_permission_denied',
                __('You cannot access this product summary.', self::TEXT_DOMAIN)
            );
        }

        $context = (new ProductReader())->read($input['product_id']);

        if ($context === null) {
            return new \WP_Error(
                'detit_ability_product_unavailable',
                __('This product is unavailable.', self::TEXT_DOMAIN)
            );
        }

        return [
            'product_id' => $context->id(),
            'title' => $context->title(),
            'type' => $context->type(),
            'has_description' => trim($context->description()) !== '',
            'has_short_description' => trim($context->shortDescription()) !== '',
            'has_featured_image' => $context->featuredImage() !== null,
            'category_count' => count($context->categories()),
            'tag_count' => count($context->tags()),
            'attribute_count' => count($context->attributes()),
        ];
    }
}
