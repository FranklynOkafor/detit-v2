<?php

declare(strict_types=1);

namespace DetIt\Catalog;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stage 61 — deliberately bounded and resumable catalog scanning.
 *
 * No cron, action scheduling or automatic bulk execution in this stage.
 * Every scan is explicitly requested and processes at most 25 products.
 */
final class BatchCatalogAuditor
{
    public const MAX_BATCH_SIZE = 25;

    /**
     * Scan the next page of WooCommerce parent products in ascending ID order.
     * Use next_after_id to resume rather than OFFSET (which can skip rows).
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function scan(int $limit = 10, int $afterProductId = 0): array|\WP_Error
    {
        $forbidden = $this->authorizationError();
        if ($forbidden !== null) {
            return $forbidden;
        }

        if (
            $limit < 1
            || $limit > self::MAX_BATCH_SIZE
            || $afterProductId < 0
        ) {
            return new \WP_Error(
                'detit_batch_invalid_range',
                'Batch size must be between 1 and 25 and the cursor cannot be negative.'
            );
        }

        global $wpdb;

        // The query only reads catalog post IDs. No direct product SQL writes.
        // Include private/draft products so authorized merchants can audit
        // non-public catalog work; exclude trash and product variations.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
                  AND post_status IN ('publish', 'private', 'draft', 'pending', 'future')
                  AND ID > %d
                ORDER BY ID ASC
                LIMIT %d",
                'product',
                $afterProductId,
                $limit + 1
            )
        );

        if ($wpdb->last_error !== '') {
            return new \WP_Error(
                'detit_batch_query_failed',
                'Unable to read the product list for the batch.'
            );
        }

        $ids = array_map('intval', is_array($ids) ? $ids : []);
        $hasMore = count($ids) > $limit;
        $pageIds = array_slice($ids, 0, $limit);
        $results = $this->processIds($pageIds);

        return [
            'mode' => 'catalog_page',
            'after_product_id' => $afterProductId,
            'next_after_id' => $pageIds === []
                ? $afterProductId
                : $pageIds[array_key_last($pageIds)],
            'has_more' => $hasMore,
            'limit' => $limit,
            ...$results,
        ];
    }

    /**
     * Audit explicitly selected IDs; useful for targeting known gaps.
     * Invalid or missing products are reported individually and do not
     * prevent a valid neighbor from being audited.
     *
     * @param array<int, mixed> $productIds
     * @return array<string, mixed>|\WP_Error
     */
    public function scanIds(array $productIds): array|\WP_Error
    {
        $forbidden = $this->authorizationError();
        if ($forbidden !== null) {
            return $forbidden;
        }

        if (
            count($productIds) < 1
            || count($productIds) > self::MAX_BATCH_SIZE
        ) {
            return new \WP_Error(
                'detit_batch_invalid_ids',
                'Supply between 1 and 25 positive WooCommerce product IDs.'
            );
        }

        $unique = [];
        foreach ($productIds as $id) {
            if (! is_int($id) || $id <= 0) {
                return new \WP_Error(
                    'detit_batch_invalid_ids',
                    'Every product ID must be a positive integer.'
                );
            }
            $unique[$id] = $id;
        }

        return [
            'mode' => 'selected_ids',
            ...$this->processIds(array_values($unique)),
        ];
    }

    /**
     * @param array<int, int> $ids
     * @return array{total:int,completed:int,failed:int,items:array<int,array<string,mixed>>}
     */
    private function processIds(array $ids): array
    {
        $auditor = new ProductCatalogAuditor();
        $completed = 0;
        $failed = 0;
        $items = [];

        foreach ($ids as $id) {
            try {
                $result = $auditor->audit($id);
            } catch (\Throwable $error) {
                // One fatal product-specific error must not abort the batch.
                $result = new \WP_Error(
                    'detit_batch_item_exception',
                    'The audit failed for this product.'
                );
            }

            if (is_wp_error($result)) {
                ++$failed;
                $items[] = [
                    'product_id' => $id,
                    'status' => 'failed',
                    'error_code' => $result->get_error_code(),
                ];
                continue;
            }

            ++$completed;
            $items[] = [
                'product_id' => $id,
                'status' => $result['status'],
                'score' => $result['health']['score'],
                'issue_count' => $result['issue_count'],
            ];
        }

        return [
            'total' => count($ids),
            'completed' => $completed,
            'failed' => $failed,
            'items' => $items,
        ];
    }

    private function authorizationError(): ?\WP_Error
    {
        if (
            ! is_user_logged_in()
            || ! current_user_can('manage_woocommerce')
        ) {
            return new \WP_Error(
                'detit_batch_forbidden',
                'You do not have permission to scan the WooCommerce catalog.'
            );
        }

        return null;
    }
}
