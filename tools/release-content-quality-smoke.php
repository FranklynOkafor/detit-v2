<?php
/**
 * Release quality contract: read-only, deterministic smoke checks.
 * No AI calls, product writes, settings updates, or database changes.
 *
 * From WordPress app/public:
 * wp eval-file wp-content/plugins/detit/tools/release-content-quality-smoke.php
 */

use DetIt\AI\AIClientGatewayInterface;
use DetIt\AI\AIClientResult;
use DetIt\AI\ContentQualityPolicy;
use DetIt\AI\GenerationRequest;
use DetIt\AI\GenerationResponseHandler;
use DetIt\AI\GenerationResponseParser;
use DetIt\AI\GenerationResult;
use DetIt\AI\OutputSchema;
use DetIt\AI\PromptBuilder;
use DetIt\AI\Templates\GeneralTemplate;
use DetIt\Domain\BrandProfile;
use DetIt\Domain\ProductFactSheet;

echo "RELEASE: FACT-AWARE CONTENT QUALITY\n";
echo "-----------------------------------\n";

$failureCount = 0;
$check = static function (string $name, bool $pass) use (&$failureCount): void {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL') . "\n";
    if (! $pass) {
        ++$failureCount;
    }
};

$richFacts = new ProductFactSheet(
    99901,
    'Stainless Steel Insulated Travel Mug',
    'MUG-01',
    'simple',
    'A reusable insulated travel mug with a stainless steel interior, a fitted lid, and a non-slip exterior. '
        . 'The 450 ml capacity is specified by the seller. The lid and insulated walls help reduce spills '
        . 'and maintain beverage temperature in ordinary travel situations. This item is available in black '
        . 'and should be cleaned by hand after use.',
    'A 450 ml insulated travel mug with a fitted lid.',
    [['id' => 10, 'name' => 'Travel mugs', 'slug' => 'travel-mugs']],
    [],
    [['name' => 'pa_colour', 'label' => 'Colour', 'options' => ['Black'], 'visible' => true, 'variation' => false]],
    '',
    ['length' => '', 'width' => '', 'height' => '']
);

$sparseFacts = new ProductFactSheet(
    99902, 'Green Drink', '', 'simple', '', '', [], [], [], '',
    ['length' => '', 'width' => '', 'height' => '']
);

$policy = new ContentQualityPolicy();
$check('RICH_PRODUCT_IDENTIFIED', $policy->hasRichFacts($richFacts));
$check('SPARSE_PRODUCT_IDENTIFIED', ! $policy->hasRichFacts($sparseFacts));

$fields = [
    OutputSchema::FIELD_SHORT_DESCRIPTION,
    OutputSchema::FIELD_DESCRIPTION,
    OutputSchema::FIELD_META_DESCRIPTION,
];

$rules = $policy->instructions($richFacts, $fields);
$check('SEO_NATURAL_KEYPHRASE_GUIDANCE', str_contains($rules, 'natural search keyphrase'));
$check('SUBHEADINGS_REQUIRED_FOR_RICH', str_contains($rules, '<h3>'));
$check('RICH_DESCRIPTION_TARGET_PRESENT', str_contains($rules, '200–350 useful words'));
$check('SEO_META_TARGET_PRESENT', str_contains($rules, '135–155 characters'));
$check('NO_INVENTED_FACTS_GUARD', str_contains($rules, 'Never keyword-stuff') && str_contains($rules, 'Do not invent'));
$check('PREVIEW_ONLY_META_DISCLOSED', str_contains($rules, 'PREVIEW-ONLY'));

$sparseRules = $policy->instructions($sparseFacts, [OutputSchema::FIELD_DESCRIPTION]);
$check('SPARSE_FACTS_DO_NOT_REQUIRE_PADDING', str_contains($sparseRules, 'NEVER pad with invented details'));
$check('UNSELECTED_META_INSTRUCTIONS_OMITTED', ! str_contains($sparseRules, 'SEO META DESCRIPTION:'));

$promptRequest = (new PromptBuilder())->build(
    new BrandProfile(['language_variant' => 'English (Nigeria)', 'tone' => 'Professional']),
    $richFacts,
    new GeneralTemplate(),
    $fields,
    'English (Nigeria)',
    'Professional'
);
$check('SEO_RULES_REACH_GENERATION_PROMPT', str_contains($promptRequest->prompt(), 'PRODUCT COPY AND SEARCH READINESS'));
$check('LONG_DESCRIPTION_HAS_OUTPUT_BUDGET', $promptRequest->maxTokens() === 2800);
$shortRequest = (new PromptBuilder())->build(
    new BrandProfile(), $sparseFacts, new GeneralTemplate(),
    [OutputSchema::FIELD_SHORT_DESCRIPTION], 'English (Nigeria)'
);
$check('SHORT_DESCRIPTION_SMALLER_OUTPUT_BUDGET', $shortRequest->maxTokens() === 1000);

$goodShort = 'This reusable insulated travel mug features a 450 ml capacity, a fitted lid, a stainless steel interior and a non-slip exterior in black for everyday beverage carrying.';
$goodLong = '<h3>Stainless steel interior</h3><p>' . str_repeat(
    'This travel mug has a stainless steel interior, a fitted lid and a 450 ml capacity. ',
    12
) . '</p><h3>Product details</h3><p>' . str_repeat(
    'The mug has a non-slip exterior and is available in black. The seller recommends hand cleaning. ',
    8
) . '</p>';

$passingResult = GenerationResult::fromArray([
    'short_description' => $goodShort,
    'description' => $goodLong,
    'meta_description' => 'Stainless steel insulated travel mug with 450 ml capacity, fitted lid and non-slip exterior. Explore the available black colour.',
], $fields);
$check('RICH_DESCRIPTION_MINIMUM_PASS', $policy->issues($passingResult, $richFacts, $fields) === []);

$weakResult = GenerationResult::fromArray([
    'short_description' => 'Great mug.',
    'description' => 'This mug is great.',
    'meta_description' => 'Great mug.',
], $fields);
$check('WEAK_RICH_COPY_REJECTED', count($policy->issues($weakResult, $richFacts, $fields)) >= 2);
$check('SPARSE_COPY_NOT_PADDED_FOR_WORD_COUNT', $policy->issues($weakResult, $sparseFacts, $fields) === []);

$goodJson = (string) wp_json_encode([
    'short_description' => $goodShort,
    'description' => $goodLong,
    'meta_description' => 'Stainless steel insulated travel mug with fitted lid and 450 ml capacity.',
], JSON_UNESCAPED_UNICODE);
$weakJson = (string) wp_json_encode([
    'short_description' => 'Great mug.',
    'description' => 'Great mug.',
    'meta_description' => 'Great mug.',
], JSON_UNESCAPED_UNICODE);

$fakeGateway = new class([$weakJson, $goodJson]) implements AIClientGatewayInterface {
    private array $replies;
    public int $calls = 0;

    public function __construct(array $replies) { $this->replies = $replies; }
    public function supportsImageInput(): bool { return false; }
    public function generate(GenerationRequest $request): AIClientResult
    {
        $this->calls++;
        $json = $this->replies[$this->calls - 1] ?? '{}';
        return AIClientResult::success($json, 'fake', 'test');
    }
};

$outcome = (new GenerationResponseHandler($fakeGateway, new GenerationResponseParser()))
    ->handle($promptRequest, $fields, $richFacts);

$check('QUALITY_REPAIR_SUCCEEDED', $outcome->isSuccess());
$check('QUALITY_REPAIR_ONLY_ONE_RETRY', $fakeGateway->calls === 2);

$failedGateway = new class([$weakJson, $weakJson]) implements AIClientGatewayInterface {
    private array $replies;
    public int $calls = 0;
    public function __construct(array $replies) { $this->replies = $replies; }
    public function supportsImageInput(): bool { return false; }
    public function generate(GenerationRequest $request): AIClientResult
    {
        $this->calls++;
        return AIClientResult::success($this->replies[$this->calls - 1] ?? '{}', 'fake', 'test');
    }
};

$failedOutcome = (new GenerationResponseHandler($failedGateway, new GenerationResponseParser()))
    ->handle($promptRequest, $fields, $richFacts);

$check('QUALITY_STAYS_FAILED_AFTER_ONE_REPAIR', $failedOutcome->isFailure());
$check('NO_UNBOUNDED_RETRY', $failedGateway->calls === 2);

echo 'RESULT: ' . ($failureCount === 0 ? 'PASS' : 'FAIL (' . $failureCount . ')') . "\n";
