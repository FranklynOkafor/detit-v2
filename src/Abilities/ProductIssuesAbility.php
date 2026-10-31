<?php

declare(strict_types=1);

namespace DetIt\Abilities;

use DetIt\Catalog\ProductIssueDetector;
use DetIt\WooCommerce\ProductReader;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only issue detection exposed through WordPress Abilities.
 * Shares Stage 56's authorization rules; no AI or product writes.
 */
final class ProductIssuesAbility
{
    public const NAME = 'detit/get-product-issues';

    private const TEXT_DOMAIN = 'detit-product-content-generator-for-woocommerce';

    public function registerHooks(): void
    {
        if (! function_exists('wp_register_ability')) {
            return;
        }

        add_action(
            'wp_abilities_api_init',
            [$this, 'registerAbility']
        );
    }

    public function registerAbility(): void
    {
        wp_register_ability(
            self::NAME,
            [
                'label' => __('Get Product Catalog Issues', self::TEXT_DOMAIN),
                'description' => __(
                    'Detect missing or incomplete WooCommerce catalog information without modifying the product.',
                    self::TEXT_DOMAIN
                ),
                'category' => 'detit-catalog',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                    ],
                    'required' => ['product_id'],
                    'additionalProperties' => false,
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'issue_count' => ['type' => 'integer'],
                        'issues' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string'],
                                    'field' => ['type' => 'string'],
                                    'severity' => [
                                        'type' => 'string',
                                        'enum' => ['high', 'medium', 'info'],
                                    ],
                                    'message' => ['type' => 'string'],
                                ],
                                'required' => ['code', 'field', 'severity', 'message'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['product_id', 'issue_count', 'issues'],
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
     * Reuse the existing secure per-product permission boundary.
     *
     * @param array<string, mixed> $input
     */
    public function canRead(array $input): bool
    {
        return (new ProductSummaryAbility())->canRead($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|\WP_Error
     */
    public function execute(array $input): array|\WP_Error
    {
        if (! $this->canRead($input)) {
            return new \WP_Error(
                'detit_issues_permission_denied',
                __('You cannot access these product issues.', self::TEXT_DOMAIN)
            );
        }

        $context = (new ProductReader())->read($input['product_id']);

        if ($context === null) {
            return new \WP_Error(
                'detit_issues_product_unavailable',
                __('This product is unavailable.', self::TEXT_DOMAIN)
            );
        }

        $issues = (new ProductIssueDetector())->detect($context);

        return [
            'product_id' => $context->id(),
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }
}
