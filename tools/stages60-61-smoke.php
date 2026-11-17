<?php

/**
 * Stages 60–61: real LocalWP database integration smoke test.
 *
 * Run from the WordPress root:
 * wp eval-file wp-content/plugins/detit/tools/stages60-61-smoke.php
 *
 * WARNING: Writes/refreshes DetIt's wp_detit_product_index audit rows for
 * product 46 and a handful of catalog products. It does NOT edit WooCommerce
 * product fields and does NOT call an AI provider.
 */

use DetIt\Catalog\BatchCatalogAuditor;
use DetIt\Catalog\ProductCatalogAuditor;
use DetIt\Catalog\ProductHealthScorer;
use DetIt\Catalog\ProductIssueDetector;
use DetIt\Catalog\ProductSourceHasher;
use DetIt\Storage\ProductIndexRepository;
use DetIt\WooCommerce\ProductReader;

$failures = 0;
$check = static function (string $name, bool $pass) use (&$failures): void {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $pass) {
        ++$failures;
    }
};

echo "STAGES 60–61: PERSISTENT PRODUCT AND BATCH AUDITS\n";
echo "------------------------------------------------\n";

$originalUser = get_current_user_id();
$auditor = new ProductCatalogAuditor();
$batch = new BatchCatalogAuditor();
$index = new ProductIndexRepository();
$reader = new ProductReader();

try {
    wp_set_current_user(0);

    $check('ANONYMOUS_SINGLE_AUDIT_BLOCKED', is_wp_error($auditor->audit(46)));
    $check('ANONYMOUS_BATCH_BLOCKED', is_wp_error($batch->scan(2, 0)));
    $check('ANONYMOUS_SELECTED_BATCH_BLOCKED', is_wp_error($batch->scanIds([46])));

    $adminId = 0;
    foreach (get_users([
        'role' => 'administrator',
        'fields' => 'ID',
        'number' => 100,
    ]) as $candidate) {
        if (
            user_can((int) $candidate, 'manage_woocommerce')
            && user_can((int) $candidate, 'edit_post', 46)
        ) {
            $adminId = (int) $candidate;
            break;
        }
    }

    if ($adminId === 0) {
        echo "ADMIN_NOT_FOUND: An administrator able to edit product 46 is required.\n";
        echo "RESULT: INCOMPLETE\n";
        return;
    }

    wp_set_current_user($adminId);

    $contextBefore = $reader->read(46);
    if ($contextBefore === null) {
        echo "PRODUCT_46_NOT_FOUND: Choose a valid disposable test product.\n";
        echo "RESULT: INCOMPLETE\n";
        return;
    }

    $beforeData = $contextBefore->toArray();
    $storedBefore = $index->findByProduct(46);
    $expectedIssues = (new ProductIssueDetector())->detect($contextBefore);
    $expectedHealth = (new ProductHealthScorer())->score($expectedIssues);
    $expectedHash = (new ProductSourceHasher())->hash($contextBefore);

    $check('INVALID_SINGLE_PRODUCT_ID_REJECTED', is_wp_error($auditor->audit(0)));
    $check('NONEXISTENT_SINGLE_PRODUCT_REJECTED', is_wp_error($auditor->audit(999999999)));

    $first = $auditor->audit(46);
    $check('SINGLE_AUDIT_SUCCESS', is_array($first));
    $check('SINGLE_AUDIT_STATUS_VALID', is_array($first)
        && in_array($first['status'], ['created', 'updated', 'unchanged'], true));
    $check('SINGLE_AUDIT_CORRECT_SCORE', is_array($first)
        && $first['health'] === $expectedHealth);
    $check('SINGLE_AUDIT_CORRECT_ISSUES', is_array($first)
        && $first['issues'] === $expectedIssues);
    $check('SINGLE_AUDIT_CORRECT_HASH', is_array($first)
        && $first['source_hash'] === $expectedHash);

    $storedAfter = $index->findByProduct(46);
    $check('PERSISTED_INDEX_ROW_EXISTS', is_array($storedAfter));
    $check('PERSISTED_HEALTH_MATCHES', is_array($storedAfter)
        && (int) $storedAfter['content_health'] === $expectedHealth['score']);
    $check('PERSISTED_ISSUES_MATCH', is_array($storedAfter)
        && $storedAfter['issue_flags'] === $expectedIssues);
    $check('PERSISTED_HASH_MATCHES', is_array($storedAfter)
        && $storedAfter['source_hash'] === $expectedHash);
    $check('PERSISTED_SCAN_TIMESTAMP', is_array($storedAfter)
        && ! empty($storedAfter['last_scanned_at']));
    $check('OPTIMIZATION_TIMESTAMP_UNCHANGED', is_array($storedAfter)
        && ($storedAfter['last_optimized_at'] ?? null) === ($storedBefore['last_optimized_at'] ?? null));

    $second = $auditor->audit(46);
    $check('REPEAT_AUDIT_MARKED_UNCHANGED', is_array($second)
        && $second['status'] === 'unchanged');

    $check('BATCH_SIZE_ZERO_REJECTED', is_wp_error($batch->scan(0, 0)));
    $check('BATCH_SIZE_ABOVE_LIMIT_REJECTED', is_wp_error($batch->scan(26, 0)));
    $check('NEGATIVE_CURSOR_REJECTED', is_wp_error($batch->scan(2, -1)));
    $check('EMPTY_SELECTED_IDS_REJECTED', is_wp_error($batch->scanIds([])));
    $check('TOO_MANY_SELECTED_IDS_REJECTED', is_wp_error($batch->scanIds(range(1, 26))));
    $check('STRING_PRODUCT_ID_REJECTED', is_wp_error($batch->scanIds(['46'])));

    $selected = $batch->scanIds([46, 999999999, 46]);
    $check('SELECTED_IDS_BATCH_RETURNS', is_array($selected));
    $check('SELECTED_IDS_DEDUPLICATED', is_array($selected)
        && $selected['total'] === 2);
    $check('FAILED_ITEM_DOES_NOT_STOP_BATCH', is_array($selected)
        && $selected['completed'] === 1
        && $selected['failed'] === 1);
    $check('FAILED_ITEM_HAS_ERROR_CODE', is_array($selected)
        && count($selected['items']) === 2
        && $selected['items'][1]['status'] === 'failed'
        && $selected['items'][1]['error_code'] === 'detit_audit_product_not_found');

    $page1 = $batch->scan(2, 0);
    $check('FIRST_CATALOG_PAGE_RETURNED', is_array($page1)
        && $page1['total'] <= 2 && $page1['total'] >= 1);
    $check('PAGE_LIMIT_ENFORCED', is_array($page1)
        && count($page1['items']) <= 2);
    $check('PAGE_COUNTERS_CONSISTENT', is_array($page1)
        && $page1['total'] === $page1['completed'] + $page1['failed']);
    $check('PAGE_CURSOR_ADVANCES', is_array($page1)
        && $page1['next_after_id'] > 0);

    if (is_array($page1) && $page1['has_more']) {
        $page2 = $batch->scan(2, $page1['next_after_id']);
        $check('SECOND_CATALOG_PAGE_RETURNED', is_array($page2));
        $check('CURSOR_PAGES_DO_NOT_OVERLAP', is_array($page2)
            && count(array_intersect(
                array_column($page1['items'], 'product_id'),
                array_column($page2['items'], 'product_id')
            )) === 0);
        $check('SECOND_PAGE_CURSOR_ADVANCES', is_array($page2)
            && $page2['next_after_id'] > $page1['next_after_id']);
    } else {
        echo "SECOND_PAGE: SKIPPED (catalog has no additional matching products)\n";
    }

    $end = $batch->scan(2, 999999999);
    $check('EMPTY_FINAL_PAGE_IS_VALID', is_array($end)
        && $end['total'] === 0
        && $end['has_more'] === false);

    $contextAfter = $reader->read(46);
    $check('WOO_PRODUCT_CONTENT_UNCHANGED', $contextAfter !== null
        && $contextAfter->toArray() === $beforeData);

    echo 'PRODUCT_46_ISSUES: ' . wp_json_encode(array_column($expectedIssues, 'code')) . PHP_EOL;
    echo 'PRODUCT_46_SCORE: ' . $expectedHealth['score'] . PHP_EOL;
    echo 'PRODUCT_46_SOURCE_HASH: ' . $expectedHash . PHP_EOL;

    if (is_array($page1)) {
        echo 'BATCH_PAGE_1: ' . wp_json_encode($page1) . PHP_EOL;
    }
    echo 'RESULT: ' . ($failures === 0 ? 'PASS' : 'FAIL (' . $failures . ')') . PHP_EOL;
} finally {
    wp_set_current_user($originalUser);
}
