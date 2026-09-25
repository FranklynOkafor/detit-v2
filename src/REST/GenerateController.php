<?php

declare(strict_types=1);

namespace DetIt\REST;

use DetIt\Application\GenerateProduct;
use DetIt\AI\OutputSchema;
use DetIt\AI\Templates\TemplateRegistry;


// use DetIt\AI\GenerationResponseHandler;
// use DetIt\AI\GenerationResponseParser;

// use DetIt\AI\PromptBuilder;
// use DetIt\AI\WordPressAIClientGateway;
// use DetIt\Domain\BrandProfile;
// use DetIt\WooCommerce\ProductFactSheetFactory;
// use DetIt\WooCommerce\ProductReader;

final class GenerateController
{
    private const NAMESPACE = 'detit/v2';
    private const ROUTE = '/generate';

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
                'callback'            => [$this, 'generate'],
                'permission_callback' => [$this, 'permissionsCheck'],
                'args'                => $this->getArguments(),
            ]
        );
    }

    /**
     * Stage 39 generation endpoint permission boundary.
     */
    public function permissionsCheck(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You must be logged in to generate product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 401]
            );
        }

        if (! current_user_can('manage_woocommerce')) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You are not allowed to generate product content.',
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

    public function generate(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        try {
            $productId = (int) $request->get_param(
                'product_id'
            );

            $selectedFields = array_values(
                array_unique(
                    $request->get_param('selected_fields')
                )
            );

            $templateSlug = (string) $request->get_param(
                'template'
            );

            $language = (string) $request->get_param(
                'language'
            );

            $tone = (string) $request->get_param(
                'tone'
            );

            $additionalInstructions = (string) $request->get_param(
                'additional_instructions'
            );

            /*
         * Stage 40 application service owns the complete
         * product-generation workflow.
         */
            $generateProduct = new GenerateProduct();

            $outcome = $generateProduct->execute(
                $productId,
                $selectedFields,
                $templateSlug,
                $language,
                $tone,
                $additionalInstructions
            );

            /*
         * The REST layer only translates application
         * outcomes into HTTP responses.
         */
            if ($outcome->isFailure()) {
                return new \WP_Error(
                    $outcome->errorCode()
                        ?? 'UNKNOWN_ERROR',
                    $outcome->errorMessage()
                        ?? __(
                            'DetIt could not generate product content.',
                            'detit-product-content-generator-for-woocommerce'
                        ),
                    [
                        'status' => 502,
                    ]
                );
            }

            $result = $outcome->result();

            if ($result === null) {
                return new \WP_Error(
                    'INVALID_AI_RESPONSE',
                    __(
                        'DetIt did not receive a usable generation result.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    [
                        'status' => 502,
                    ]
                );
            }

            return new \WP_REST_Response(
                $result->toArray(),
                200
            );
        } catch (\InvalidArgumentException $exception) {
            return new \WP_Error(
                'detit_invalid_generation_request',
                $exception->getMessage(),
                [
                    'status' => 400,
                ]
            );
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    '[DetIt GenerateController] '
                        . $exception->getMessage()
                );
            }

            return new \WP_Error(
                'detit_generation_error',
                __(
                    'DetIt could not complete the generation request.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                [
                    'status' => 500,
                ]
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

            'selected_fields' => [
                'required'          => true,
                'type'              => 'array',
                'items'             => [
                    'type' => 'string',
                ],
                'validate_callback' => [
                    $this,
                    'validateSelectedFields',
                ],
            ],

            'template' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => [
                    $this,
                    'validateTemplate',
                ],
            ],

            'language' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static function ($value): bool {
                    return trim((string) $value) !== '';
                },
            ],

            'tone' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],

            'additional_instructions' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }

    public function validateSelectedFields(
        mixed $value,
        \WP_REST_Request $request,
        string $param
    ): bool|\WP_Error {

        if (! is_array($value) || $value === []) {
            return new \WP_Error(
                'rest_invalid_param',
                __(
                    'At least one generation field must be selected.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 400]
            );
        }

        $allowedFields = OutputSchema::fields();

        foreach ($value as $field) {
            if (
                ! is_string($field)
                || $field === ''
                || ! in_array($field, $allowedFields, true)
            ) {
                return new \WP_Error(
                    'rest_invalid_param',
                    __(
                        'One or more selected generation fields are invalid.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 400]
                );
            }
        }

        return true;
    }


    public function validateTemplate(
        mixed $value,
        \WP_REST_Request $request,
        string $param
    ): bool|\WP_Error {

        if (! is_string($value)) {
            return false;
        }

        $slug = sanitize_key($value);

        $registry = new TemplateRegistry();

        if ($registry->get($slug) === null) {
            return new \WP_Error(
                'rest_invalid_param',
                __(
                    'The selected generation template is invalid.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 400]
            );
        }

        return true;
    }
}
