<?php

/**
 * Stage 62 dashboard usability: full-catalog search and deterministic sort.
 *
 * Run from WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/stage62-search-sort-smoke.php
 *
 * Pure reads: NO DetIt index writes, product changes or AI calls.
 */

use DetIt\Admin\ContentHealthPage;
use DetIt\Storage\ContentHealthDashboardRepository;

$failed = 0;
$check = static function (string $name, bool $ok) use (&$failed): void {
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $ok) {
        ++$failed;
    }
};

echo "STAGE 62: DASHBOARD SEARCH AND SORT\n";
echo "-----------------------------------\n";

$previous = get_current_user_id();
$oldGet = $_GET;

try {
    wp_set_current_user(0);
    $repository = new ContentHealthDashboardRepository();

    $check('ANONYMOUS_SEARCH_DENIED', is_wp_error(
        $repository->browse('all', 1, 'Green Drink', 'score_asc')
    ));

    $adminId = 0;
    foreach (get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 100]) as $id) {
        if (
            user_can((int) $id, 'manage_woocommerce')
            && user_can((int) $id, 'edit_post', 46)
        ) {
            $adminId = (int) $id;
            break;
        }
    }
    if ($adminId === 0 || get_post_type(46) !== 'product') {
        echo "ADMIN_OR_PRODUCT_46_UNAVAILABLE\nRESULT: INCOMPLETE\n";
        return;
    }
    wp_set_current_user($adminId);

    $original = $repository->browse('all', 1);
    $check('BASE_CATALOG_LIST_AVAILABLE', is_array($original));

    $title = (string) get_the_title(46);
    $check('TEST_PRODUCT_HAS_TITLE', trim($title) !== '');

    $byTitle = $repository->browse('all', 1, $title, 'name_asc');
    $check('SEARCH_BY_PRODUCT_TITLE', is_array($byTitle)
        && in_array(46, array_column($byTitle['products'], 'product_id'), true));

    $byId = $repository->browse('all', 1, '46', 'id_asc');
    $check('SEARCH_BY_EXACT_PRODUCT_ID', is_array($byId)
        && in_array(46, array_column($byId['products'], 'product_id'), true));

    $nonsense = $repository->browse('all', 1, 'definitely_not_a_real_product_91a3', 'name_asc');
    $check('NONMATCHING_SEARCH_EMPTY', is_array($nonsense)
        && $nonsense['matched'] === 0
        && $nonsense['products'] === []);
    $check('SEARCH_COUNTS_BEFORE_PAGINATION', is_array($byTitle)
        && $byTitle['matched'] >= count($byTitle['products']));
    $check('INVALID_SORT_FALLS_BACK', is_array($repository->browse('all', 1, '', 'DROP TABLE'))
        && $repository->browse('all', 1, '', 'DROP TABLE')['sort'] === 'id_asc');

    $getAll = static function (string $sort) use ($repository): array {
        $items = [];
        $page = 1;
        do {
            $result = $repository->browse('all', $page, '', $sort);
            if (! is_array($result)) {
                return [];
            }
            array_push($items, ...$result['products']);
            ++$page;
        } while ($page <= $result['pages']);
        return $items;
    };

    $az = $getAll('name_asc');
    $za = $getAll('name_desc');
    $low = $getAll('score_asc');
    $high = $getAll('score_desc');

    $extractNames = static fn (array $list): array => array_column($list, 'title');
    $ascendingNames = $extractNames($az);
    $descendingNames = $extractNames($za);

    $check('ALPHABETICAL_ASCENDING', $ascendingNames !== []
        && $ascendingNames === (static function (array $values): array {
            usort($values, 'strcasecmp');
            return $values;
        })($ascendingNames));
    $check('ALPHABETICAL_DESCENDING', $descendingNames !== []
        && $descendingNames === (static function (array $values): array {
            usort($values, static fn ($a, $b): int => strcasecmp($b, $a));
            return $values;
        })($descendingNames));

    $isOrdered = static function (array $items, bool $ascending): bool {
        $previousScore = null;
        $unscannedSeen = false;
        foreach ($items as $row) {
            if (! $row['scanned']) {
                $unscannedSeen = true;
                continue;
            }
            if ($unscannedSeen) {
                return false;
            }
            $score = (int) $row['score'];
            if ($previousScore !== null) {
                if ($ascending && $score < $previousScore) {
                    return false;
                }
                if (! $ascending && $score > $previousScore) {
                    return false;
                }
            }
            $previousScore = $score;
        }
        return true;
    };

    $check('SCORE_ASCENDING_ACROSS_PAGES', $low !== []
        && $isOrdered($low, true));
    $check('SCORE_DESCENDING_ACROSS_PAGES', $high !== []
        && $isOrdered($high, false));

    $check('ALL_SORTS_SAME_MATCH_COUNT', is_array($original)
        && count($az) === $original['matched']
        && count($za) === $original['matched']
        && count($low) === $original['matched']
        && count($high) === $original['matched']);

    $_GET = [
        'page' => ContentHealthPage::SLUG,
        'product_search' => $title,
        'sort' => 'score_desc',
        'audit_filter' => 'all',
    ];

    ob_start();
    try {
        (new ContentHealthPage())->renderPage();
        $html = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }

    $check('DASHBOARD_SEARCH_FIELD_RENDERED', str_contains($html, 'name="product_search"'));
    $check('DASHBOARD_SORT_FIELD_RENDERED', str_contains($html, 'name="sort"'));
    $check('DASHBOARD_SEARCH_VALUE_PRESERVED', str_contains($html, 'value="' . esc_attr($title) . '"'));
    $check('DASHBOARD_SORT_CHOICE_SELECTED', preg_match('/value="score_desc"\s+selected/', $html) === 1);
    $check('DASHBOARD_CLEAR_SEARCH_AVAILABLE', str_contains($html, 'Clear search'));

    echo 'PRODUCTS_MATCHING_TITLE: ' . (is_array($byTitle) ? $byTitle['matched'] : 'query-error') . PHP_EOL;
    echo 'TOTAL_CATALOG_PRODUCTS: ' . (is_array($original) ? $original['totals']['products'] : 'query-error') . PHP_EOL;
    echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ')') . PHP_EOL;
} finally {
    wp_set_current_user($previous);
    $_GET = $oldGet;
}
