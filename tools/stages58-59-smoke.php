<?php

/**
 * Stages 58–59 — deterministic catalog completeness and source-hash tests.
 *
 * Run from WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/stages58-59-smoke.php
 *
 * In-memory fixtures only; product 46 is read for reporting, not altered.
 * No AI calls, database writes, or product mutations.
 */

use DetIt\Catalog\ProductHealthScorer;
use DetIt\Catalog\ProductIssueDetector;
use DetIt\Catalog\ProductSourceHasher;
use DetIt\WooCommerce\ProductContext;
use DetIt\WooCommerce\ProductReader;

$failed = 0;
$check = static function (string $name, bool $pass) use (&$failed): void {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL') . PHP_EOL;
    if (! $pass) {
        ++$failed;
    }
};

$sample = [
    'id' => 999,
    'title' => 'Glass Cup',
    'sku' => 'GLASS-01',
    'type' => 'simple',
    'description' => '<p>Transparent glass cup.</p>',
    'short_description' => 'A drinking cup.',
    'categories' => [
        ['id' => 2, 'name' => 'Kitchen', 'slug' => 'kitchen'],
        ['id' => 1, 'name' => 'Drinkware', 'slug' => 'drinkware'],
    ],
    'tags' => [
        ['id' => 21, 'name' => 'Tumbler', 'slug' => 'tumbler'],
        ['id' => 20, 'name' => 'Glass', 'slug' => 'glass'],
    ],
    'attributes' => [[
        'name' => 'material',
        'label' => 'Material',
        'options' => ['Glass'],
        'visible' => true,
        'variation' => false,
    ]],
    'weight' => '',
    'dimensions' => ['length' => '', 'width' => '', 'height' => ''],
    'featured_image' => [
        'id' => 42,
        'url' => 'https://example.invalid/glass.jpg',
        'alt' => 'Clear glass cup',
    ],
    'gallery_images' => [
        ['id' => 43, 'url' => 'https://example.invalid/back.jpg', 'alt' => 'Glass cup back'],
        ['id' => 44, 'url' => 'https://example.invalid/front.jpg', 'alt' => 'Glass cup front'],
    ],
];

$context = static function (array $changes = []) use ($sample): ProductContext {
    $p = array_replace($sample, $changes);

    return new ProductContext(
        $p['id'],
        $p['title'],
        $p['sku'],
        $p['type'],
        $p['description'],
        $p['short_description'],
        $p['categories'],
        $p['tags'],
        $p['attributes'],
        $p['weight'],
        $p['dimensions'],
        $p['featured_image'],
        $p['gallery_images']
    );
};

$detector = new ProductIssueDetector();
$scorer = new ProductHealthScorer();
$hasher = new ProductSourceHasher();

echo "STAGES 58–59: COMPLETENESS SCORE & SOURCE HASH\n";
echo "-----------------------------------------------\n";

$complete = $scorer->score($detector->detect($context()));
$check('COMPLETE_PRODUCT_100', $complete['score'] === 100);
$check('COMPLETE_PRODUCT_EXCELLENT', $complete['rating'] === 'excellent');
$check('COMPLETE_NO_DEDUCTIONS', $complete['deductions'] === []);
$check('SCORING_VERSION_PRESENT', $complete['rules_version'] === ProductHealthScorer::RULES_VERSION);

$optional = $scorer->score($detector->detect($context([
    'sku' => '', 'tags' => [], 'attributes' => [],
])));
$check('OPTIONAL_SIMPLE_FIELDS_NOT_PENALIZED', $optional['score'] === 100);

$description = $scorer->score($detector->detect($context(['description' => '<p>&nbsp;</p>'])));
$check('MISSING_DESCRIPTION_COSTS_20', $description['score'] === 80);

$photo = $scorer->score($detector->detect($context(['featured_image' => null])));
$check('MISSING_FEATURED_IMAGE_COSTS_25', $photo['score'] === 75);

$variable = $scorer->score($detector->detect($context([
    'type' => 'variable', 'attributes' => [],
])));
$check('VARIABLE_ATTRIBUTES_COST_15', $variable['score'] === 85);

$empty = $scorer->score($detector->detect($context([
    'title' => ' ',
    'description' => '',
    'short_description' => '',
    'featured_image' => null,
    'categories' => [],
    'tags' => [],
    'sku' => '',
    'attributes' => [],
])));
$check('EMPTY_SIMPLE_PRODUCT_SCORE_5', $empty['score'] === 5);
$check('EMPTY_SIMPLE_PRODUCT_CRITICAL', $empty['rating'] === 'critical');

$repeatIssue = [
    ['code' => 'missing_description', 'severity' => 'medium'],
    ['code' => 'missing_description', 'severity' => 'medium'],
    ['code' => 'unrecognized_future_code', 'severity' => 'high'],
];
$check('DUPLICATES_NOT_DOUBLE_PENALIZED', $scorer->score($repeatIssue)['score'] === 80);
$check('INFORMATION_ONLY_NEVER_DEDUCTS', $scorer->score([
    ['code' => 'missing_attributes', 'severity' => 'info'],
    ['code' => 'missing_sku', 'severity' => 'info'],
])['score'] === 100);

$overCap = [
    ['code' => 'missing_title', 'severity' => 'high'],
    ['code' => 'missing_featured_image', 'severity' => 'high'],
    ['code' => 'missing_description', 'severity' => 'medium'],
    ['code' => 'missing_short_description', 'severity' => 'medium'],
    ['code' => 'missing_categories', 'severity' => 'medium'],
    ['code' => 'missing_attributes', 'severity' => 'medium'],
];
$check('SCORE_CLAMPED_TO_ZERO', $scorer->score($overCap)['score'] === 0);

$baseHash = $hasher->hash($context());
$check('SHA256_HEX_LENGTH_64', preg_match('/^[a-f0-9]{64}$/D', $baseHash) === 1);
$check('HASH_IS_STABLE', $baseHash === $hasher->hash($context()));

$reorderedCategories = array_reverse($sample['categories']);
$reorderedTags = array_reverse($sample['tags']);
$check('TAXONOMY_ORDER_IRRELEVANT', $baseHash === $hasher->hash(
    $context(['categories' => $reorderedCategories, 'tags' => $reorderedTags])
));

$reorderedAttributes = [
    [
        'variation' => false, 'options' => ['Glass'],
        'name' => 'material', 'visible' => true, 'label' => 'Material',
    ],
];
$check('ASSOCIATIVE_KEY_ORDER_IRRELEVANT', $baseHash === $hasher->hash(
    $context(['attributes' => $reorderedAttributes])
));

$changes = [
    'title' => 'Different Product',
    'sku' => 'CHANGED-01',
    'description' => '<p>Updated description.</p>',
    'short_description' => 'Updated summary.',
    'weight' => '2',
    'dimensions' => ['length' => '22', 'width' => '', 'height' => ''],
    'categories' => [],
    'tags' => [],
    'attributes' => [],
    'featured_image' => null,
    'gallery_images' => [],
];

foreach ($changes as $field => $newValue) {
    $check('HASH_CHANGES_' . strtoupper($field), $baseHash !== $hasher->hash(
        $context([$field => $newValue])
    ));
}

$check('HASH_CHANGES_IMAGE_ALT', $baseHash !== $hasher->hash($context([
    'featured_image' => [
        'id' => 42,
        'url' => 'https://example.invalid/glass.jpg',
        'alt' => 'New descriptive alternative text',
    ],
])));

$check('HASH_CHANGES_GALLERY_ORDER', $baseHash !== $hasher->hash($context([
    'gallery_images' => array_reverse($sample['gallery_images']),
])));

$real = (new ProductReader())->read(46);
if ($real === null) {
    echo "REAL_PRODUCT_46: NOT_AVAILABLE (synthetic tests continue)\n";
} else {
    $realIssues = $detector->detect($real);
    $realHealth = $scorer->score($realIssues);
    $realHash = $hasher->hash($real);
    $check('REAL_PRODUCT_SCORE_VALID', $realHealth['score'] >= 0 && $realHealth['score'] <= 100);
    $check('REAL_PRODUCT_HASH_VALID', preg_match('/^[a-f0-9]{64}$/D', $realHash) === 1);
    echo 'REAL_PRODUCT_46_ISSUES: ' . wp_json_encode(array_column($realIssues, 'code')) . PHP_EOL;
    echo 'REAL_PRODUCT_46_HEALTH: ' . wp_json_encode($realHealth) . PHP_EOL;
    echo 'REAL_PRODUCT_46_HASH: ' . $realHash . PHP_EOL;
}

echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ')') . PHP_EOL;
