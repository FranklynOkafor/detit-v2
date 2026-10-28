<?php

/**
 * Stage 56 — WordPress Abilities API smoke test.
 *
 * Run from the WordPress root:
 * wp eval-file wp-content/plugins/detit/tools/stage56-ability-smoke.php
 *
 * Reads disposable WooCommerce test product #46 (change below if needed).
 * Does not modify product data or send requests to an AI provider.
 *
 * The script temporarily switches the CLI request's current WP user.
 * It does not change user accounts or permissions.
 */

use DetIt\Abilities\ProductSummaryAbility;

$productId = 46;
$failures = 0;

$check = static function (string $label, bool $pass) use (&$failures): void {
    echo $label . ': ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $pass) {
        ++$failures;
    }
};

echo "STAGE 56 WORDPRESS ABILITY SMOKE TEST\n";
echo "-------------------------------------\n";

if (
    ! function_exists('wp_get_ability')
    || ! class_exists('WP_Ability')
) {
    echo 'ABILITY_API_UNAVAILABLE: WordPress 6.9+ is required.' . PHP_EOL;
    return;
}

$ability = wp_get_ability(ProductSummaryAbility::NAME);
$check('ABILITY_REGISTERED', $ability instanceof \WP_Ability);

if (! $ability instanceof \WP_Ability) {
    echo 'RESULT: FAIL (ability was not registered)' . PHP_EOL;
    return;
}

// The documented metadata must not create a public REST endpoint.
$meta = $ability->get_meta();
$check('REST_EXPOSURE_DISABLED', ($meta['show_in_rest'] ?? false) === false);
$check('READ_ONLY_ANNOTATION', ($meta['annotations']['readonly'] ?? false) === true);

// Verify anonymous users cannot execute even a valid product request.
wp_set_current_user(0);
$denied = $ability->execute(['product_id' => $productId]);
$check('ANONYMOUS_DENIED', is_wp_error($denied));

// Find an existing administrator authorized to edit the test product.
$users = get_users([
    'role' => 'administrator',
    'fields' => 'ID',
    'number' => 100,
]);

$authorizedId = 0;
foreach ($users as $userId) {
    if (
        user_can((int) $userId, 'manage_woocommerce')
        && user_can((int) $userId, 'edit_post', $productId)
    ) {
        $authorizedId = (int) $userId;
        break;
    }
}

if ($authorizedId === 0) {
    echo "AUTHORIZED_USER_NOT_FOUND: Select an existing test product ID and administrator.\n";
    echo "RESULT: INCOMPLETE\n";
    return;
}

wp_set_current_user($authorizedId);
$summary = $ability->execute(['product_id' => $productId]);

$check('AUTHORIZED_READ', is_array($summary));

if (is_array($summary)) {
    $expectedKeys = [
        'product_id', 'title', 'type', 'has_description',
        'has_short_description', 'has_featured_image',
        'category_count', 'tag_count', 'attribute_count',
    ];
    $check('SCHEMA_KEYS_MATCH', array_keys($summary) === $expectedKeys);
    $check('PRODUCT_ID_MATCHES', $summary['product_id'] === $productId);
    $check('SUMMARY_TITLE_NONEMPTY', trim((string) $summary['title']) !== '');
    echo 'PRODUCT_SUMMARY: ' . wp_json_encode($summary) . PHP_EOL;
}

// WordPress's own Ability validation must reject malformed inputs.
$invalid = $ability->execute(['product_id' => '46']);
$check('WRONG_TYPE_REJECTED', is_wp_error($invalid));

$extra = $ability->execute([
    'product_id' => $productId,
    'forbidden_extra_field' => true,
]);
$check('UNEXPECTED_FIELD_REJECTED', is_wp_error($extra));

$missing = $ability->execute([]);
$check('MISSING_PRODUCT_ID_REJECTED', is_wp_error($missing));

$notFound = $ability->execute(['product_id' => 999999999]);
$check('MISSING_PRODUCT_DENIED', is_wp_error($notFound));

echo 'RESULT: ' . ($failures === 0 ? 'PASS' : 'FAIL (' . $failures . ')') . PHP_EOL;
