<?php

declare(strict_types=1);

namespace DetIt\REST;

use DetIt\Storage\GenerationRepository;
use DetIt\WooCommerce\ProductSnapshot;
use DetIt\WooCommerce\ProductWriter;

if (! defined('ABSPATH')) {
    exit;
}

final class UndoController
{
    private const NAMESPACE = 'detit/v2';

    private const ROUTE = '/undo';

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
                'callback'            => [$this, 'undo'],
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
                    'You must be logged in to undo product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 401]
            );
        }

        if (! current_user_can('manage_woocommerce')) {
            return new \WP_Error(
                'PERMISSION_DENIED',
                __(
                    'You are not allowed to undo product content.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 403]
            );
        }

        $generationId = absint(
            $request->get_param('generation_id')
        );

        $repository = new GenerationRepository();

        $generation = $repository->find(
            $generationId
        );

        if ($generation === null) {
            return new \WP_Error(
                'GENERATION_NOT_FOUND',
                __(
                    'The requested generation could not be found.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 404]
            );
        }

        $productId = (int) (
            $generation['product_id'] ?? 0
        );

        $post = get_post($productId);

        if (
            ! $post instanceof \WP_Post
            || 'product' !== $post->post_type
        ) {
            return new \WP_Error(
                'PRODUCT_NOT_FOUND',
                __(
                    'The product for this generation could not be found.',
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

    public function undo(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        try {
            $generationId = (int) $request->get_param(
                'generation_id'
            );

            $repository = new GenerationRepository();

            $generation = $repository->find(
                $generationId
            );

            if ($generation === null) {
                return new \WP_Error(
                    'GENERATION_NOT_FOUND',
                    __(
                        'The requested generation could not be found.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 404]
                );
            }

            /*
             * Undo is only valid for a successful Apply.
             */
            if (
                ($generation['operation'] ?? null) !== 'apply'
                || ($generation['status'] ?? null) !== 'completed'
            ) {
                return new \WP_Error(
                    'UNDO_NOT_AVAILABLE',
                    __(
                        'This generation cannot be undone.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 409]
                );
            }

            $productId = (int) (
                $generation['product_id'] ?? 0
            );

            $beforeSnapshot =
                $generation['before_snapshot'] ?? null;

            $afterSnapshot =
                $generation['after_snapshot'] ?? null;

            $appliedFields =
                $generation['applied_fields'] ?? null;

            /*
             * Safe Undo requires all three pieces:
             *
             * before_snapshot = what we restore
             * after_snapshot  = what we compare against
             * applied_fields  = fields involved in this Apply
             */
            if (
                ! is_array($beforeSnapshot)
                || ! is_array($afterSnapshot)
                || ! is_array($appliedFields)
                || $appliedFields === []
            ) {
                return new \WP_Error(
                    'UNDO_RECOVERY_DATA_MISSING',
                    __(
                        'This generation does not contain enough recovery data to be safely undone.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 409]
                );
            }

            /*
             * Capture what WooCommerce contains right now.
             */
            $snapshotReader = new ProductSnapshot();

            $currentSnapshot = $snapshotReader->capture(
                $productId,
                $appliedFields
            );

            if ($currentSnapshot === false) {
                return new \WP_Error(
                    'CURRENT_STATE_READ_FAILED',
                    __(
                        'DetIt could not safely read the current product state.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 500]
                );
            }

            /*
             * Critical Undo safety check.
             *
             * If the product no longer matches the state
             * DetIt originally left behind, someone or
             * something has changed it since the Apply.
             *
             * Do not overwrite those newer changes.
             */
            if (
                ! $this->snapshotsMatch(
                    $currentSnapshot,
                    $afterSnapshot
                )
            ) {
                return new \WP_Error(
                    'UNDO_CONFLICT',
                    __(
                        'This product has changed since DetIt applied the generation. Undo was stopped to protect the newer changes.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 409]
                );
            }

            /*
             * The current product still matches DetIt's
             * after-state, so restoring the before-state
             * is safe.
             */
            $writer = new ProductWriter();

            if (
                ! $writer->write(
                    $productId,
                    $beforeSnapshot
                )
            ) {
                return new \WP_Error(
                    'UNDO_WRITE_FAILED',
                    __(
                        'DetIt could not restore the previous product content.',
                        'detit-product-content-generator-for-woocommerce'
                    ),
                    ['status' => 500]
                );
            }

            /*
             * The original Apply has now been successfully
             * reversed.
             */
            $historyMarkedUndone =
                $repository->update(
                    $generationId,
                    [
                        'status' => 'undone',
                    ]
                );

            /*
             * The product has already been restored.
             *
             * If history finalization fails, do not falsely
             * claim that the Undo itself failed.
             */
            if (
                ! $historyMarkedUndone
                && defined('WP_DEBUG')
                && WP_DEBUG
            ) {
                error_log(
                    '[DetIt UndoController] '
                        . 'Generation '
                        . $generationId
                        . ' was restored, but could not '
                        . 'be marked as undone.'
                );
            }

            return new \WP_REST_Response(
                [
                    'success'       => true,
                    'product_id'    => $productId,
                    'generation_id' => $generationId,
                    'fields'        => $appliedFields,
                ],
                200
            );
        } catch (\Throwable $exception) {
            if (
                defined('WP_DEBUG')
                && WP_DEBUG
            ) {
                error_log(
                    '[DetIt UndoController] '
                        . $exception->getMessage()
                );
            }

            return new \WP_Error(
                'detit_undo_error',
                __(
                    'DetIt could not undo the generation.',
                    'detit-product-content-generator-for-woocommerce'
                ),
                ['status' => 500]
            );
        }
    }

    private function getArguments(): array
    {
        return [
            'generation_id' => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Compare two product snapshots safely.
     *
     * Tag order is ignored because WordPress may return
     * taxonomy terms in a different order even when the
     * actual tag set has not changed.
     */
    private function snapshotsMatch(
        array $currentSnapshot,
        array $afterSnapshot
    ): bool {
        return $this->normalizeSnapshot($currentSnapshot)
            === $this->normalizeSnapshot($afterSnapshot);
    }

    /**
     * Normalize snapshot structure before comparison.
     */
    private function normalizeSnapshot(
        array $snapshot
    ): array {
        if (
            isset($snapshot['tags'])
            && is_array($snapshot['tags'])
        ) {
            sort(
                $snapshot['tags'],
                SORT_STRING
            );
        }

        ksort($snapshot);

        return $snapshot;
    }
}