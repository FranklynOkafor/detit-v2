<?php

declare(strict_types=1);

namespace DetIt\Catalog;

use DetIt\Storage\ProductIndexRepository;
use DetIt\WooCommerce\ProductReader;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stage 60 — audit one WooCommerce product and persist ONLY its DetIt index.
 *
 * Never changes WooCommerce product fields, invokes a text model or generates
 * content. Authorization is enforced even for direct PHP callers.
 */
final class ProductCatalogAuditor
{
    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function audit(int $productId): array|\WP_Error
    {
        if (
            ! is_user_logged_in()
            || ! current_user_can('manage_woocommerce')
        ) {
            return new \WP_Error(
                'detit_audit_forbidden',
                'You do not have permission to audit WooCommerce products.'
            );
        }

        if ($productId <= 0) {
            return new \WP_Error(
                'detit_audit_invalid_id',
                'A positive WooCommerce product ID is required.'
            );
        }

        $post = get_post($productId);

        if (
            ! $post instanceof \WP_Post
            || $post->post_type !== 'product'
        ) {
            return new \WP_Error(
                'detit_audit_product_not_found',
                'The specified WooCommerce product was not found.'
            );
        }

        if (! current_user_can('edit_post', $productId)) {
            return new \WP_Error(
                'detit_audit_product_forbidden',
                'You do not have permission to audit this product.'
            );
        }

        $context = (new ProductReader())->read($productId);

        if ($context === null) {
            return new \WP_Error(
                'detit_audit_product_unavailable',
                'Unable to read this WooCommerce product.'
            );
        }

        try {
            $issues = (new ProductIssueDetector())->detect($context);
            $health = (new ProductHealthScorer())->score($issues);
            $sourceHash = (new ProductSourceHasher())->hash($context);
        } catch (\Throwable $error) {
            return new \WP_Error(
                'detit_audit_computation_failed',
                'Unable to compute this product audit.'
            );
        }

        $index = new ProductIndexRepository();
        $existing = $index->findByProduct($productId);
        $unchanged = $existing !== null
            && $existing['source_hash'] === $sourceHash
            && ($existing['rules_version'] ?? '') === ProductHealthScorer::RULES_VERSION
            && (int) $existing['content_health'] === $health['score']
            && $existing['issue_flags'] === $issues
            && ! empty($existing['last_scanned_at']);

        $status = $existing === null
            ? 'created'
            : ($unchanged ? 'unchanged' : 'updated');

        // Store UTC, consistent with existing DetIt repository timestamps.
        $scannedAt = current_time('mysql', true);
        $saved = $index->save(
            $productId,
            [
                'content_health' => $health['score'],
                'issue_flags' => $issues,
                'source_hash' => $sourceHash,
                'rules_version' => ProductHealthScorer::RULES_VERSION,
                'last_scanned_at' => $scannedAt,
            ]
        );

        if (! $saved) {
            return new \WP_Error(
                'detit_audit_save_failed',
                'Unable to save the audit to the DetIt product index.'
            );
        }

        // "Unchanged" means product content AND computed assessment match.
        // last_scanned_at is refreshed to reflect this successful scan.
        return [
            'product_id' => $productId,
            'status' => $status,
            'health' => $health,
            'source_hash' => $sourceHash,
            'issue_count' => count($issues),
            'issues' => $issues,
            'scanned_at' => $scannedAt,
        ];
    }
}
