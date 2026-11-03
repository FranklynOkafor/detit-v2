<?php

/**
 * UX regression: modal defaults come from the existing DetIt Brand Profile.
 *
 * Run from WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/brand-profile-generator-defaults-smoke.php
 *
 * Read-only. Never sends a generation request or writes product settings.
 */

use DetIt\Admin\ProductGenerateMetaBox;

$failed = 0;
$check = static function (string $name, bool $pass) use (&$failed): void {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $pass) {
        ++$failed;
    }
};

echo "GENERATE MODAL: SAVED BRAND PROFILE DEFAULTS\n";
echo "---------------------------------------------\n";

$profileBefore = get_option('detit_brand_profile', []);
$profile = is_array($profileBefore) ? $profileBefore : [];

$expectedLanguage = isset($profile['language_variant']) && is_scalar($profile['language_variant'])
    ? sanitize_text_field((string) $profile['language_variant'])
    : '';
$expectedTone = isset($profile['tone']) && is_scalar($profile['tone'])
    ? sanitize_text_field((string) $profile['tone'])
    : '';

$product = get_post(46);
if (! $product instanceof WP_Post || $product->post_type !== 'product') {
    echo "PRODUCT_46_REQUIRED: Use an existing disposable product.\n";
    echo "RESULT: INCOMPLETE\n";
    return;
}

ob_start();
try {
    (new ProductGenerateMetaBox())->render($product);
    $markup = (string) ob_get_contents();
} finally {
    ob_end_clean();
}

$inputById = static function (string $html, string $id): ?string {
    $pattern = '/<input\b[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/s';

    return preg_match($pattern, $html, $match) === 1
        ? $match[0]
        : null;
};

$languageInput = $inputById($markup, 'detit-language');
$toneInput = $inputById($markup, 'detit-tone');

$check('LANGUAGE_INPUT_RENDERED', is_string($languageInput));
$check('TONE_INPUT_RENDERED', is_string($toneInput));
$check('LANGUAGE_PREFILLED_FROM_BRAND_PROFILE', is_string($languageInput)
    && str_contains($languageInput, 'value="' . esc_attr($expectedLanguage) . '"'));
$check('TONE_PREFILLED_FROM_BRAND_PROFILE', is_string($toneInput)
    && str_contains($toneInput, 'value="' . esc_attr($expectedTone) . '"'));

$check('LANGUAGE_OVERRIDE_REMAINS_EDITABLE', is_string($languageInput)
    && ! str_contains($languageInput, 'readonly')
    && ! str_contains($languageInput, 'disabled'));
$check('TONE_OVERRIDE_REMAINS_EDITABLE', is_string($toneInput)
    && ! str_contains($toneInput, 'readonly')
    && ! str_contains($toneInput, 'disabled'));
$check('PROFILE_SOURCE_EXPLAINED', str_contains($markup, 'prefilled from your saved DetIt Brand Profile'));
$check('GENERATION_CONTROLS_PRESERVED', str_contains($markup, 'id="detit-prepare-generation"')
    && str_contains($markup, 'id="detit-template"'));
$check('BRAND_PROFILE_OPTION_UNCHANGED', get_option('detit_brand_profile', []) === $profileBefore);

echo 'SAVED_LANGUAGE: ' . $expectedLanguage . PHP_EOL;
echo 'SAVED_TONE: ' . $expectedTone . PHP_EOL;
echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ')') . PHP_EOL;
