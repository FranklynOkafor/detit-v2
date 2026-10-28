<?php

/**
 * Stage 57 — deterministic WooCommerce catalog issue detection smoke test.
 *
 * Run from the WordPress root:
 * wp eval-file wp-content/plugins/detit/tools/stage57-issues-smoke.php
 *
 * All synthetic fixtures are in memory. The real product check reads #46
 * but never writes data or calls AI.
 */

use DetIt\Catalog\ProductIssueDetector;
use DetIt\Abilities\ProductIssuesAbility;
use DetIt\WooCommerce\ProductContext;
use DetIt\WooCommerce\ProductReader;

$errors = 0;
$check = static function (string $name, bool $condition) use (&$errors): void {
    echo $name . ': ' . ($condition ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $condition) {
        ++$errors;
    }
};

$makeContext = static function (array $overrides = []): ProductContext {
    $values = array_merge([
        'type' => 'simple',
        'title' => 'Clear Glass Tumbler',
        'sku' => 'GLASS-001',
        'description' => '<p>Clear reusable drinking glass.</p>',
        'short_description' => '<p>Transparent drinking tumbler.</p>',
        'categories' => [['id' => 1, 'name' => 'Drinkware', 'slug' => 'drinkware']],
        'tags' => [['id' => 2, 'name' => 'Tumbler', 'slug' => 'tumbler']],
        'attributes' => [[
            'name' => 'material',
            'label' => 'Material',
            'options' => ['Glass'],
            'visible' => true,
            'variation' => false,
        ]],
        'featured_image' => [
            'id' => 31,
            'url' => 'https://example.invalid/glass.jpg',
            'alt' => 'Clear glass tumbler',
        ],
    ], $overrides);

    return new ProductContext(
        999,
        $values['title'],
        $values['sku'],
        $values['type'],
        $values['description'],
        $values['short_description'],
        $values['categories'],
        $values['tags'],
        $values['attributes'],
        '',
        ['length' => '', 'width' => '', 'height' => ''],
        $values['featured_image'],
        []
    );
};

$detector = new ProductIssueDetector();
$get = static fn (array $issues): array => array_column($issues, 'code');

echo "STAGE 57 CATALOG ISSUE DETECTION\n";
echo "--------------------------------\n";

$complete = $detector->detect($makeContext());
$check('COMPLETE_PRODUCT_NO_ISSUES', $complete === []);

$empty = $detector->detect($makeContext([
    'title' => ' ',
    'sku' => '',
    'description' => '<p>&nbsp;</p>',
    'short_description' => '<div>&#8203;</div>',
    'categories' => [],
    'tags' => [],
    'attributes' => [],
    'featured_image' => null,
]));
$check('EMPTY_PRODUCT_HAS_EIGHT_ISSUES', count($empty) === 8);
$check('EMPTY_PRODUCT_FLAGS_TITLE', in_array('missing_title', $get($empty), true));
$check('EMPTY_PRODUCT_FLAGS_HTML_ONLY_DESCRIPTION', in_array('missing_description', $get($empty), true));
$check('EMPTY_PRODUCT_FLAGS_INVISIBLE_SHORT_DESCRIPTION', in_array('missing_short_description', $get($empty), true));
$check('EMPTY_PRODUCT_FLAGS_FEATURED_IMAGE', in_array('missing_featured_image', $get($empty), true));
$check('EMPTY_PRODUCT_FLAGS_CATEGORY', in_array('missing_categories', $get($empty), true));
$check('OPTIONAL_TAGS_SUGGESTED_ONLY', ($empty[5]['severity'] ?? '') === 'info');
$check('OPTIONAL_SKU_SUGGESTED_ONLY', ($empty[6]['severity'] ?? '') === 'info');
$check('OPTIONAL_ATTRIBUTES_SUGGESTED_ONLY', ($empty[7]['severity'] ?? '') === 'info');
$check('ISSUES_HAVE_EXPECTED_KEYS', array_keys($empty[0] ?? []) === ['code', 'field', 'severity', 'message']);

$noAlt = $detector->detect($makeContext([
    'featured_image' => [
        'id' => 31,
        'url' => 'https://example.invalid/glass.jpg',
        'alt' => '',
    ],
]));
$check('MISSING_ALT_INFORMATIONAL', count($noAlt) === 1
    && $noAlt[0]['code'] === 'missing_image_alt'
    && $noAlt[0]['severity'] === 'info');

$badUrl = $detector->detect($makeContext([
    'featured_image' => ['id' => 31, 'url' => '', 'alt' => 'Product'],
]));
$check('UNUSABLE_IMAGE_URL_FLAGGED', count($badUrl) === 1
    && $badUrl[0]['code'] === 'missing_featured_image');

$variable = $detector->detect($makeContext([
    'type' => 'variable',
    'attributes' => [],
]));
$check('VARIABLE_WITHOUT_ATTRIBUTES_WARNED', count($variable) === 1
    && $variable[0]['code'] === 'missing_attributes'
    && $variable[0]['severity'] === 'medium');

$real = (new ProductReader())->read(46);
if ($real === null) {
    echo "REAL_PRODUCT_46: NOT_FOUND (synthetic tests still apply)\n";
} else {
    $issues = $detector->detect($real);
    $check('REAL_PRODUCT_ISSUES_STRUCTURED', count(array_filter(
        $issues,
        static fn (array $issue): bool =>
            isset($issue['code'], $issue['severity'], $issue['field'], $issue['message'])
            && in_array($issue['severity'], ['high', 'medium', 'info'], true)
    )) === count($issues));
    echo 'REAL_PRODUCT_46_ISSUE_CODES: ' . wp_json_encode($get($issues)) . PHP_EOL;
}


// Integration: the Stage 56 Abilities API remains protected for issue access.
$ability = function_exists('wp_get_ability')
    ? wp_get_ability(ProductIssuesAbility::NAME)
    : null;

$check('ISSUE_ABILITY_REGISTERED', $ability instanceof \WP_Ability);

if ($ability instanceof \WP_Ability) {
    $meta = $ability->get_meta();
    $check('ISSUE_ABILITY_NOT_PUBLIC_REST', ($meta['show_in_rest'] ?? false) === false);
    $check('ISSUE_ABILITY_READ_ONLY', ($meta['annotations']['readonly'] ?? false) === true);

    wp_set_current_user(0);
    $check('ISSUE_ABILITY_DENIES_ANONYMOUS', is_wp_error(
        $ability->execute(['product_id' => 46])
    ));

    $authorizedId = 0;
    foreach (get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 100]) as $candidate) {
        if (
            user_can((int) $candidate, 'manage_woocommerce')
            && user_can((int) $candidate, 'edit_post', 46)
        ) {
            $authorizedId = (int) $candidate;
            break;
        }
    }

    if ($authorizedId > 0 && $real !== null) {
        wp_set_current_user($authorizedId);
        $result = $ability->execute(['product_id' => 46]);
        $check('ISSUE_ABILITY_AUTHORIZED_READ', is_array($result));
        $check('ISSUE_ABILITY_MATCHES_DETECTOR', is_array($result)
            && $result['issue_count'] === count($detector->detect($real))
            && $result['issues'] === $detector->detect($real));
        $check('ISSUE_ABILITY_REJECTS_WRONG_TYPE', is_wp_error(
            $ability->execute(['product_id' => '46'])
        ));
        $check('ISSUE_ABILITY_REJECTS_EXTRA_FIELDS', is_wp_error(
            $ability->execute(['product_id' => 46, 'unexpected' => true])
        ));
        $check('ISSUE_ABILITY_DENIES_INVALID_PRODUCT', is_wp_error(
            $ability->execute(['product_id' => 999999999])
        ));
    } else {
        echo "ISSUE_ABILITY_AUTHORIZED_CHECK: SKIPPED (admin or product 46 unavailable)\n";
    }
}

echo 'RESULT: ' . ($errors === 0 ? 'PASS' : 'FAIL (' . $errors . ')') . PHP_EOL;
