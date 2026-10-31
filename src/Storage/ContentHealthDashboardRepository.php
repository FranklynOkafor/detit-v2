<?php

declare(strict_types=1);

namespace DetIt\Storage;

use DetIt\Catalog\ProductHealthScorer;
use DetIt\Catalog\ProductSourceHasher;
use DetIt\WooCommerce\ProductReader;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only, paginated dashboard queries over products + DetIt audit index.
 * The view does NOT update audit state. Only explicit POST actions scan.
 */
final class ContentHealthDashboardRepository
{
    public const PAGE_SIZE = 20;

    private const FILTERS = ['all', 'scanned', 'unscanned', 'needs_attention'];

    public function browse(string $filter = 'all', int $page = 1): array|\WP_Error
    {
        if (
            ! is_user_logged_in()
            || ! current_user_can('manage_woocommerce')
        ) {
            return new \WP_Error('detit_health_forbidden', 'Catalog access denied.');
        }

        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $page = max(1, $page);

        global $wpdb;

        $table = $wpdb->prefix . 'detit_product_index';

        // Every filter comes from a fixed local allowlist, not raw user SQL.
        $where = "p.post_type = 'product'
            AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')";

        if ($filter === 'scanned') {
            $where .= ' AND i.last_scanned_at IS NOT NULL';
        } elseif ($filter === 'unscanned') {
            $where .= ' AND i.last_scanned_at IS NULL';
        } elseif ($filter === 'needs_attention') {
            $where .= ' AND i.last_scanned_at IS NOT NULL AND i.content_health < 70';
        }

        $from = "FROM {$wpdb->posts} p
            LEFT JOIN {$table} i ON i.product_id = p.ID";

        $totalsSql = "SELECT
                COUNT(*) AS products,
                SUM(CASE WHEN i.last_scanned_at IS NOT NULL THEN 1 ELSE 0 END) AS scanned,
                SUM(CASE WHEN i.last_scanned_at IS NULL THEN 1 ELSE 0 END) AS unscanned,
                SUM(CASE WHEN i.last_scanned_at IS NOT NULL
                    AND i.content_health < 70 THEN 1 ELSE 0 END) AS needs_attention
            {$from}
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')";

        $totals = $wpdb->get_row($totalsSql, ARRAY_A);
        if (! is_array($totals) || $wpdb->last_error !== '') {
            return new \WP_Error('detit_health_query_failed', 'Unable to read audit summary.');
        }

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) {$from} WHERE {$where}"
        );
        if ($wpdb->last_error !== '') {
            return new \WP_Error('detit_health_query_failed', 'Unable to count audit rows.');
        }

        $pages = max(1, (int) ceil($count / self::PAGE_SIZE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID AS product_id, p.post_title AS title,
                    i.content_health, i.issue_flags, i.source_hash,
                    i.rules_version, i.last_scanned_at
                 {$from}
                 WHERE {$where}
                 ORDER BY p.ID ASC
                 LIMIT %d OFFSET %d",
                self::PAGE_SIZE,
                $offset
            ),
            ARRAY_A
        );

        if (! is_array($raw) || $wpdb->last_error !== '') {
            return new \WP_Error('detit_health_query_failed', 'Unable to list product audits.');
        }

        $reader = new ProductReader();
        $hasher = new ProductSourceHasher();
        $products = [];

        foreach ($raw as $row) {
            $productId = (int) $row['product_id'];

            // Additional per-product permission check. Do not disclose
            // product titles or issues for posts the user cannot edit.
            if (! current_user_can('edit_post', $productId)) {
                continue;
            }

            $scanned = ! empty($row['last_scanned_at']);
            $decoded = $scanned && is_string($row['issue_flags'])
                ? json_decode($row['issue_flags'], true)
                : null;
            $issuesValid = is_array($decoded) && array_is_list($decoded);

            $stale = false;
            if ($scanned) {
                $stale = ($row['rules_version'] ?? '') !== ProductHealthScorer::RULES_VERSION
                    || ! $issuesValid
                    || ! is_string($row['source_hash'])
                    || preg_match('/^[a-f0-9]{64}$/D', $row['source_hash']) !== 1;

                // Source drift must be checked against live WooCommerce data.
                if (! $stale) {
                    try {
                        $context = $reader->read($productId);
                        $stale = $context === null
                            || $hasher->hash($context) !== $row['source_hash'];
                    } catch (\Throwable $error) {
                        $stale = true;
                    }
                }
            }

            $products[] = [
                'product_id' => $productId,
                'title' => (string) $row['title'],
                'scanned' => $scanned,
                'stale' => $stale,
                'score' => $scanned ? (int) $row['content_health'] : null,
                'issues' => $issuesValid ? $decoded : [],
                'last_scanned_at' => $scanned ? (string) $row['last_scanned_at'] : null,
            ];
        }

        return [
            'filter' => $filter,
            'page' => $page,
            'pages' => $pages,
            'matched' => $count,
            'totals' => [
                'products' => (int) $totals['products'],
                'scanned' => (int) $totals['scanned'],
                'unscanned' => (int) $totals['unscanned'],
                'needs_attention' => (int) $totals['needs_attention'],
            ],
            'products' => $products,
        ];
    }
}
