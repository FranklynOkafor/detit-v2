<?php

declare(strict_types=1);

namespace DetIt\REST;

use DetIt\WooCommerce\ProductWriter;

if (! defined('ABSPATH')) {
    exit;
}

final class ApplyController
{
    private const NAMESPACE = 'detit/v2';

    private const ROUTE = '/apply';

    private const ALLOWED_FIELDS = [
        'title',
        'short_description',
        'description',
        'tags',
    ];

    public function registerHooks(): void
    {
        add_action(
            'rest_api_init',
            [$this, 'registerRoutes']
        );
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'apply'],
                'permission_callback' => [$this, 'permissionsCheck'],
                'args'                => $this->getArguments(),
            ]
        );
    }

    public function permissionsCheck(
        \WP_REST_Request $request
    ): bool|\WP_Error {
        if (! is_user_logged_in()) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You must be logged in to apply product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 401]
            );
        }

        if (! current_user_can('manage_woocommerce')) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You are not allowed to apply product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 403]
            );
        }

        $productId = absint(
            $request->get_param('product_id')
        );

        $post = get_post($productId);

        if (
            ! $post instanceof \WP_Post
            || 'product' !== $post->post_type
        ) {
            return new \WP_Error(
                'PRODUCT_NOT_FOUND',
                __(
                    'The requested product could not be found.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 404]
            );
        }

        if (! current_user_can('edit_post', $productId)) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You are not allowed to edit this product.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 403]
            );
        }

        return true;
    }

    public function apply(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        try {
            $productId = (int) $request->get_param(
                'product_id'
            );

            $fields = $request->get_param('fields');

            if (! is_array($fields)) {
                return new \WP_Error(
                    'INVALID_APPLY_REQUEST',
                    __(
                        'The selected product content is invalid.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 400]
                );
            }

            $writer = new ProductWriter();

            if (! $writer->write($productId, $fields)) {
                return new \WP_Error(
                    'PRODUCT_WRITE_FAILED',
                    __(
                        'DetIt could not apply the selected product content.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 400]
                );
            }

            return new \WP_REST_Response(
                [
                    'success'    => true,
                    'product_id' => $productId,
                    'fields'     => array_keys($fields),
                ],
                200
            );
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    '[DetIt ApplyController] '
                        . $exception->getMessage()
                );
            }

            return new \WP_Error(
                'detit_apply_error',
                __(
                    'DetIt could not apply the selected product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 500]
            );
        }
    }

    private function getArguments(): array
    {
        return [
            'product_id' => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],

            'fields' => [
                'required'          => true,
                'type'              => 'object',
                'validate_callback' => [
                    $this,
                    'validateFields',
                ],
            ],
        ];
    }

    public function validateFields(
        mixed $value,
        \WP_REST_Request $request,
        string $param
    ): bool|\WP_Error {
        if (! is_array($value) || $value === []) {
            return new \WP_Error(
                'rest_invalid_param',
                __(
                    'At least one generated field must be selected.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 400]
            );
        }

        foreach ($value as $field => $fieldValue) {
            if (
                ! is_string($field)
                || ! in_array(
                    $field,
                    self::ALLOWED_FIELDS,
                    true
                )
            ) {
                return new \WP_Error(
                    'rest_invalid_param',
                    __(
                        'One or more selected fields cannot be applied.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 400]
                );
            }

            if ('tags' === $field) {
                if (! is_array($fieldValue)) {
                    return new \WP_Error(
                        'rest_invalid_param',
                        __(
                            'Generated tags must be a list.',
                            'detit-product-content-generator-for-woocommerce'
                        ),
                        ['status' => 400]
                    );
                }

                foreach ($fieldValue as $tag) {
                    if (! is_string($tag)) {
                        return new \WP_Error(
                            'rest_invalid_param',
                            __(
                                'Each generated tag must be text.',
                                'detit-product-content-generator-for-woocommerce'
                            ),
                            ['status' => 400]
                        );
                    }
                }

                continue;
            }

            if (! is_string($fieldValue)) {
                return new \WP_Error(
                    'rest_invalid_param',
                    __(
                        'Generated product content must be text.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 400]
                );
            }
        }

        return true;
    }
}