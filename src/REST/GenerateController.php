<?php

declare(strict_types=1);

namespace DetIt\REST;

use DetIt\AI\GenerationResponseHandler;
use DetIt\AI\GenerationResponseParser;
use DetIt\AI\OutputSchema;
use DetIt\AI\PromptBuilder;
use DetIt\AI\Templates\TemplateRegistry;
use DetIt\AI\WordPressAIClientGateway;
use DetIt\Domain\BrandProfile;
use DetIt\WooCommerce\ProductFactSheetFactory;
use DetIt\WooCommerce\ProductReader;

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
     * Stage 38 permission boundary.
     *
     * Stage 39 will replace this with:
     * - DetIt capability check
     * - product validation
     * - edit permission for the specific product
     */
    public function permissionsCheck(
        \WP_REST_Request $request
    ): bool {
        return is_user_logged_in();
    }

    public function generate(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        try {
            /*
             * ---------------------------------------------------------
             * 1. Read request inputs.
             * ---------------------------------------------------------
             */

            $productId = (int) $request->get_param(
                'product_id'
            );

            $requestedFields = $request->get_param(
                'selected_fields'
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
             * ---------------------------------------------------------
             * 2. Keep only fields supported by DetIt's first schema.
             *
             * Strict validation/error reporting belongs to Stage 39.
             * For Stage 38 we simply normalize to known fields.
             * ---------------------------------------------------------
             */

            $requestedFields = is_array($requestedFields)
                ? array_map('strval', $requestedFields)
                : [];

            $selectedFields = array_values(
                array_intersect(
                    OutputSchema::fields(),
                    $requestedFields
                )
            );

            if ($selectedFields === []) {
                return new \WP_Error(
                    'detit_no_generation_fields',
                    __(
                        'Select at least one supported field to generate.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    [
                        'status' => 400,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * 3. Read WooCommerce product.
             * ---------------------------------------------------------
             */

            $reader = new ProductReader();

            $productContext = $reader->read(
                $productId
            );

            if ($productContext === null) {
                return new \WP_Error(
                    'detit_product_not_found',
                    __(
                        'The requested product could not be found.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    [
                        'status' => 404,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * 4. Convert ProductContext into trusted AI facts.
             * ---------------------------------------------------------
             */

            $factSheetFactory = new ProductFactSheetFactory();

            $factSheet = $factSheetFactory->create(
                $productContext
            );

            /*
             * ---------------------------------------------------------
             * 5. Load the saved Brand Profile.
             * ---------------------------------------------------------
             */

            $brandData = get_option(
                'detit_brand_profile',
                []
            );

            if (!is_array($brandData)) {
                $brandData = [];
            }

            $brandProfile = new BrandProfile(
                $brandData
            );

            /*
             * ---------------------------------------------------------
             * 6. Resolve the requested template.
             * ---------------------------------------------------------
             */

            $templateRegistry = new TemplateRegistry();

            $template = $templateRegistry->get(
                $templateSlug
            );

            if ($template === null) {
                return new \WP_Error(
                    'detit_invalid_template',
                    __(
                        'The requested DetIt template does not exist.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    [
                        'status' => 400,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * 7. Build the generation request.
             * ---------------------------------------------------------
             */

            $promptBuilder = new PromptBuilder();

            $generationRequest = $promptBuilder->build(
                $brandProfile,
                $factSheet,
                $template,
                $selectedFields,
                $language,
                $tone,
                $additionalInstructions
            );

            /*
             * ---------------------------------------------------------
             * 8. Run through the real AI gateway AND Stage 37's
             *    structured-output protection.
             * ---------------------------------------------------------
             */

            $responseHandler = new GenerationResponseHandler(
                new WordPressAIClientGateway(),
                new GenerationResponseParser()
            );

            $outcome = $responseHandler->handle(
                $generationRequest,
                $selectedFields
            );

            /*
             * ---------------------------------------------------------
             * 9. Translate DetIt AI failure into a REST error.
             * ---------------------------------------------------------
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

            /*
             * ---------------------------------------------------------
             * 10. Return trusted GenerationResult content only.
             * ---------------------------------------------------------
             */

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
            /*
             * Do not leak PHP exception details to REST clients.
             * Log them for development instead.
             */
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(
                    '[DetIt Stage 38] '
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
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],

            'selected_fields' => [
                'required' => true,
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],

            'template' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],

            'language' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],

            'tone' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ],

            'additional_instructions' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => '',
            ],
        ];
    }
}