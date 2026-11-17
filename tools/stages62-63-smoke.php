<?php

/**
 * Stages 62–63: dashboard and audit schema integration test.
 *
 * Run from WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/stages62-63-smoke.php
 *
 * Writes and repairs DetIt audit rows for test product #46; does NOT
 * modify WooCommerce product content or invoke an AI provider.
 */

use DetIt\Admin\ContentHealthPage;
use DetIt\Catalog\ProductCatalogAuditor;
use DetIt\Catalog\ProductHealthScorer;
use DetIt\Storage\ContentHealthDashboardRepository;
use DetIt\Storage\ProductIndexRepository;
use DetIt\WooCommerce\ProductReader;

$failed = 0;
$check = static function (string $name, bool $pass) use (&$failed): void {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $pass) {
        ++$failed;
    }
};

echo "STAGES 62–63: CONTENT HEALTH DASHBOARD & DATABASE\n";
echo "------------------------------------------------\n";

global $wpdb;
$indexTable = $wpdb->prefix . 'detit_product_index';

$check('DB_VERSION_1_6_0', (string) get_option('detit_db_version') === '1.6.0');
$column = $wpdb->get_row(
    $wpdb->prepare("SHOW COLUMNS FROM {$indexTable} LIKE %s", 'rules_version'),
    ARRAY_A
);
$check('SCORING_RULES_VERSION_COLUMN_EXISTS', is_array($column));
$check('SCORING_RULES_VERSION_DEFAULT_EMPTY', is_array($column)
    && (string) ($column['Default'] ?? '') === '');

$originalUser = get_current_user_id();
$originalGet = $_GET;
$index = new ProductIndexRepository();
$auditor = new ProductCatalogAuditor();
$dashboard = new ContentHealthDashboardRepository();
$reader = new ProductReader();

try {
    wp_set_current_user(0);
    $check('ANONYMOUS_DASHBOARD_BLOCKED', is_wp_error($dashboard->browse()));

    $adminId = 0;
    foreach (get_users([
        'role' => 'administrator', 'fields' => 'ID', 'number' => 100,
    ]) as $id) {
        if (
            user_can((int) $id, 'manage_woocommerce')
            && user_can((int) $id, 'edit_post', 46)
        ) {
            $adminId = (int) $id;
            break;
        }
    }

    if ($adminId === 0) {
        echo "ADMIN_NOT_FOUND: Need an admin allowed to edit product 46.\n";
        echo "RESULT: INCOMPLETE\n";
        return;
    }

    wp_set_current_user($adminId);
    $before = $reader->read(46);

    if ($before === null) {
        echo "PRODUCT_46_MISSING: Use an existing disposable WooCommerce product.\n";
        echo "RESULT: INCOMPLETE\n";
        return;
    }

    $productBefore = $before->toArray();
    $prior = $index->findByProduct(46);
    $priorOptimizedAt = $prior['last_optimized_at'] ?? null;

    // New audits must persist the version that produced the score.
    $first = $auditor->audit(46);
    $stored = $index->findByProduct(46);
    $check('AUDIT_SUCCESS', is_array($first));
    $check('RULES_VERSION_PERSISTED', is_array($stored)
        && ($stored['rules_version'] ?? '') === ProductHealthScorer::RULES_VERSION);
    $check('HEALTH_SCORE_PERSISTED', is_array($first) && is_array($stored)
        && (int) $stored['content_health'] === $first['health']['score']);
    $check('ISSUE_LIST_PERSISTED', is_array($first) && is_array($stored)
        && $stored['issue_flags'] === $first['issues']);
    $check('SOURCE_HASH_PERSISTED', is_array($first) && is_array($stored)
        && $stored['source_hash'] === $first['source_hash']);
    $check('OPTIMIZED_TIMESTAMP_PRESERVED', is_array($stored)
        && ($stored['last_optimized_at'] ?? null) === $priorOptimizedAt);

    $same = $auditor->audit(46);
    $check('REPEAT_AUDIT_UNCHANGED', is_array($same) && $same['status'] === 'unchanged');

    // Legacy index rows with an old or absent version must be recalculated.
    $legacyWritten = $index->save(46, ['rules_version' => '']);
    $check('SIMULATED_LEGACY_VERSION_SAVED', $legacyWritten);
    $updated = $auditor->audit(46);
    $check('LEGACY_RULES_VERSION_TRIGGERS_UPDATE', is_array($updated)
        && $updated['status'] === 'updated');
    $check('LEGACY_ROW_UPGRADED', ($index->findByProduct(46)['rules_version'] ?? '') === ProductHealthScorer::RULES_VERSION);

    // Dashboard never scans automatically; its page should render read-only.
    $beforeView = $index->findByProduct(46);
    $data = $dashboard->browse('all', 1);
    $check('DASHBOARD_LIST_QUERY_SUCCESS', is_array($data));
    $check('DASHBOARD_TOTALS_VALID', is_array($data)
        && $data['totals']['products'] >= 1
        && $data['totals']['scanned'] >= 1
        && $data['totals']['products'] === $data['totals']['scanned'] + $data['totals']['unscanned']);
    $check('DASHBOARD_PAGINATION_BOUND', is_array($data)
        && count($data['products']) <= ContentHealthDashboardRepository::PAGE_SIZE);
    $check('INVALID_FILTER_FALLS_BACK_TO_ALL', is_array($dashboard->browse('invalid-filter', 1))
        && $dashboard->browse('invalid-filter', 1)['filter'] === 'all');
    $check('LARGE_PAGE_CLAMPED', is_array($dashboard->browse('all', 999999))
        && $dashboard->browse('all', 999999)['page'] <= $dashboard->browse('all', 999999)['pages']);
    $check('SCANNED_FILTER_WORKS', is_array($dashboard->browse('scanned', 1)));
    $check('UNSCANNED_FILTER_WORKS', is_array($dashboard->browse('unscanned', 1)));
    $check('NEEDS_ATTENTION_FILTER_WORKS', is_array($dashboard->browse('needs_attention', 1)));

    $_GET = ['page' => ContentHealthPage::SLUG];
    ob_start();
    try {
        (new ContentHealthPage())->renderPage();
        $html = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }

    $check('DASHBOARD_RENDERS_TITLE', str_contains($html, 'DetIt Content Health'));
    $check('DASHBOARD_RENDERS_BATCH_FORM', str_contains($html, 'detit_health_scan_batch'));
    $check('DASHBOARD_RENDERS_NO_AI_DISCLOSURE', str_contains($html, 'AI credits'));
    $check('BATCH_POST_ACTION_REGISTERED', has_action('admin_post_detit_health_scan_batch') !== false);
    $check('PRODUCT_POST_ACTION_REGISTERED', has_action('admin_post_detit_health_scan_product') !== false);
    $check('GET_DID_NOT_SAVE_AUDIT', $beforeView === $index->findByProduct(46));

    // Explicitly corrupt a stored source fingerprint; display must say stale.
    $check('STALENESS_FIXTURE_WRITE', $index->save(46, [
        'source_hash' => str_repeat('0', 64),
    ]));
    $staleFound = null;
    $page = 1;
    do {
        $pageData = $dashboard->browse('scanned', $page);
        if (! is_array($pageData)) {
            break;
        }
        foreach ($pageData['products'] as $product) {
            if ($product['product_id'] === 46) {
                $staleFound = $product['stale'];
                break 2;
            }
        }
        ++$page;
    } while ($page <= min(5, $pageData['pages']));

    $check('CHANGED_SOURCE_HASH_MARKED_STALE', $staleFound === true);
    $restored = $auditor->audit(46);
    $check('STALE_AUDIT_REFRESHED', is_array($restored)
        && $restored['status'] === 'updated');
    $check('RULES_VERSION_AFTER_REFRESH', ($index->findByProduct(46)['rules_version'] ?? '') === ProductHealthScorer::RULES_VERSION);

    $rows = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$indexTable} WHERE product_id = %d", 46)
    );
    $check('UNIQUE_PRODUCT_INDEX_ROW', $rows === 1);

    $after = $reader->read(46);
    $check('WOO_PRODUCT_CONTENT_UNCHANGED', $after !== null
        && $after->toArray() === $productBefore);

    $saved = $index->findByProduct(46);
    $check('OPTIMIZATION_TIMESTAMP_STILL_UNCHANGED', is_array($saved)
        && ($saved['last_optimized_at'] ?? null) === $priorOptimizedAt);

    echo 'PRODUCT_46_HEALTH: ' . wp_json_encode([
        'score' => (int) $saved['content_health'],
        'rules_version' => $saved['rules_version'],
        'issues' => array_column($saved['issue_flags'], 'code'),
        'source_hash' => $saved['source_hash'],
    ]) . PHP_EOL;

    echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ')') . PHP_EOL;
} finally {
    // Recover any artificial stale hash if a test exits early.
    if (isset($adminId) && $adminId > 0 && isset($before) && $before !== null) {
        wp_set_current_user($adminId);
        $auditor->audit(46);
    }
    wp_set_current_user($originalUser);
    $_GET = $originalGet;
}
