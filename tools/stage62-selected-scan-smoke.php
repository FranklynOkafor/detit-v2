<?php

/**
 * Stage 62 — selected-product bulk scanning UI and input validation.
 *
 * From WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/stage62-selected-scan-smoke.php
 *
 * This test is read-only. It validates the user interface and selected-ID
 * parser without triggering any scan, AI request or database update.
 */

use DetIt\Admin\ContentHealthPage;
use DetIt\Catalog\BatchCatalogAuditor;

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $ok) {
        ++$failures;
    }
};

echo "STAGE 62: SELECTED-PRODUCT SCAN UX\n";
echo "----------------------------------\n";

$parse = [ContentHealthPage::class, 'parseSelectedIds'];

$check('EMPTY_SELECTION_REJECTED', is_wp_error($parse([])));
$check('MISSING_SELECTION_REJECTED', is_wp_error($parse(null)));
$check('SCALAR_SELECTION_REJECTED', is_wp_error($parse('46')));
$check('INVALID_ID_REJECTED', is_wp_error($parse(['-46'])));
$check('ZERO_ID_REJECTED', is_wp_error($parse(['0'])));
$check('NON_NUMERIC_ID_REJECTED', is_wp_error($parse(['bad'])));
$check('NESTED_ID_REJECTED', is_wp_error($parse([['46']])));
$check('HUGE_ID_REJECTED', is_wp_error($parse(['9999999999999999999999999999999'])));
$check('TOO_MANY_SELECTED_REJECTED', is_wp_error($parse(array_fill(0, 26, '46'))));
$check('VALID_SELECTED_CONVERTED_TO_INTS', $parse(['46', '12']) === [46, 12]);
$check('DUPLICATES_DEDUPLICATED', $parse(['46', '46', '12']) === [46, 12]);
$check('UP_TO_25_VALID_IDS', count($parse(array_map('strval', range(1, 25)))) === 25);
$check('SCAN_IDS_BACKEND_HAS_25_LIMIT', BatchCatalogAuditor::MAX_BATCH_SIZE === 25);

$oldUser = get_current_user_id();
$oldGet = $_GET;

try {
    wp_set_current_user(0);
    $check('SELECTED_SCAN_POST_ACTION_REGISTERED',
        has_action('admin_post_detit_health_scan_selected') !== false);

    $userId = 0;
    foreach (get_users([
        'role' => 'administrator',
        'fields' => 'ID',
        'number' => 100,
    ]) as $candidate) {
        if (user_can((int) $candidate, 'manage_woocommerce')) {
            $userId = (int) $candidate;
            break;
        }
    }

    if ($userId === 0) {
        echo "ADMIN_UNAVAILABLE\nRESULT: INCOMPLETE\n";
        return;
    }
    wp_set_current_user($userId);
    $_GET = [
        'page' => ContentHealthPage::SLUG,
        'audit_filter' => 'all',
        'sort' => 'name_asc',
    ];

    ob_start();
    try {
        (new ContentHealthPage())->renderPage();
        $html = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }

    $check('SELECTED_SCAN_BUTTON_RENDERED', str_contains($html, 'Scan selected products'));
    $check('SELECTED_SCAN_FORM_POSTS_TO_ADMIN', str_contains($html, 'id="detit-selected-scan"')
        && str_contains($html, 'name="action" value="detit_health_scan_selected"'));
    $check('SELECTED_SCAN_NONCE_PRESENT', str_contains($html, 'name="_wpnonce"'));
    $check('SELECT_ALL_ON_PAGE_PRESENT', str_contains($html, 'id="detit-select-all"'));
    $check('VISIBLE_PRODUCT_CHECKBOX_PRESENT', str_contains($html, 'class="detit-select-product"')
        && str_contains($html, 'name="product_ids[]"')
        && str_contains($html, 'form="detit-selected-scan"'));
    $check('SELECTION_COUNT_PRESENT', str_contains($html, 'id="detit-selection-count"'));
    $check('VIEW_FILTERS_PRESERVED', str_contains($html, 'name="sort" value="name_asc"')
        && str_contains($html, 'name="audit_filter" value="all"'));
    $check('AUTOMATIC_BATCH_STILL_AVAILABLE', str_contains($html, 'Auto-scan: first 10 by ID'));
    $check('SELECTED_AND_AUTOMATIC_ACTIONS_DISTINCT', str_contains($html, 'detit_health_scan_selected')
        && str_contains($html, 'detit_health_scan_batch'));

    echo 'RESULT: ' . ($failures === 0 ? 'PASS' : 'FAIL (' . $failures . ')') . PHP_EOL;
} finally {
    wp_set_current_user($oldUser);
    $_GET = $oldGet;
}
