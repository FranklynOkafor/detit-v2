<?php

namespace DetIt\AI;

use DetIt\AI\Templates\TemplateInterface;
use DetIt\Domain\BrandProfile;
use DetIt\Domain\ProductFactSheet;
use InvalidArgumentException;
use RuntimeException;

if (! defined('ABSPATH')) {
	exit;
}

final class PromptBuilder
{

	/**
	 * Build a generation request.
	 *
	 * @param BrandProfile      $brandProfile
	 * @param ProductFactSheet  $productFactSheet
	 * @param TemplateInterface $template
	 * @param array<int,string> $selectedFields
	 * @param string            $language
	 * @param string            $toneOverride
	 * @param string            $additionalInstructions
	 *
	 * @return GenerationRequest
	 */
	public function build(
		BrandProfile $brandProfile,
		ProductFactSheet $productFactSheet,
		TemplateInterface $template,
		array $selectedFields,
		string $language = '',
		string $toneOverride = '',
		string $additionalInstructions = ''
	): GenerationRequest {

		$selectedFields = $this->normalizeSelectedFields(
			$selectedFields
		);

		if (empty($selectedFields)) {
			throw new InvalidArgumentException(
				'At least one generation field must be selected.'
			);
		}

		$layers = [
			$this->buildSystemLayer(),
			$this->buildBrandLayer($brandProfile),
			$this->buildTemplateLayer($template),
			$this->buildProductFactsLayer($productFactSheet),

			$this->buildTagRulesLayer(
				$selectedFields
			),

			$this->buildUserInstructionsLayer(
				$selectedFields,
				$language,
				$toneOverride,
				$additionalInstructions
			),

			$this->buildOutputRequirementsLayer(
				$selectedFields
			),
		];

		$prompt = implode(
			"\n\n",
			array_filter(
				$layers,
				static fn(string $layer): bool => '' !== trim($layer)
			)
		);

		if ('' === trim($prompt)) {
			throw new RuntimeException(
				'PromptBuilder produced an empty prompt.'
			);
		}

		return new GenerationRequest($prompt);
	}

	/**
	 * Build the system layer.
	 */
	private function buildSystemLayer(): string
	{

		return <<<PROMPT
=== SYSTEM ===
You are generating WooCommerce product content for DetIt.

Use the supplied brand profile, product facts, content template and user instructions when producing the requested content.
PROMPT;
	}

	/**
	 * Build the brand layer.
	 */
	private function buildBrandLayer(
		BrandProfile $brandProfile
	): string {

		return $this->buildJsonLayer(
			'BRAND',
			$brandProfile->to_array()
		);
	}

	/**
	 * Build the template layer.
	 */
	private function buildTemplateLayer(
		TemplateInterface $template
	): string {

		return $this->buildJsonLayer(
			'TEMPLATE',
			$template->to_array()
		);
	}

	/**
	 * Build the trusted product facts layer.
	 */
	private function buildProductFactsLayer(
		ProductFactSheet $productFactSheet
	): string {

		return $this->buildJsonLayer(
			'PRODUCT FACTS',
			$productFactSheet->toArray()
		);
	}



	/**
	 * Build tag reconciliation rules only when tags
	 * are part of the requested generation fields.
	 *
	 * @param array<int,string> $selectedFields
	 */
	private function buildTagRulesLayer(
		array $selectedFields
	): string {

		if (
			! in_array(
				OutputSchema::FIELD_TAGS,
				$selectedFields,
				true
			)
		) {
			return '';
		}

		return <<<PROMPT
=== TAG RULES ===
When generating tags, return the complete recommended final tag set for this product.

Review any existing product tags supplied in PRODUCT FACTS before producing the final tag list.

Follow these tag rules:

- Treat the returned tags array as the complete recommended final tag set for this product.

- Review the existing product tags against the PRODUCT FACTS before deciding what to keep, remove or add.

- Keep existing tags that are accurate, specific, relevant and genuinely useful for identifying this product.

- If an existing tag is weak, vague or overly broad, replace it with a clearer and more product-specific tag when the PRODUCT FACTS support a better alternative.

- Remove existing tags that are unrelated, misleading, incorrect or clearly inappropriate for this product.

- Do not keep a weak or generic tag merely because it is technically related to the product.

- Add useful missing tags when they are directly supported by the PRODUCT FACTS.

- Prefer tags that identify what the product is, its important type, material, ingredient, function, feature or use when those facts are explicitly available.

- Do not invent product features, materials, ingredients, uses, compatibility or characteristics in order to create tags.

- Every tag in the final set should add distinct value. Remove duplicates, near-duplicates and redundant tags that express essentially the same meaning.

- When both a broad tag and a more specific tag communicate the same idea, prefer the more specific tag unless the broad tag adds meaningful value.

- Avoid vague or generic tags such as "item", "product", "good product", "nice", "quality", "food", "drink", "refreshment" or similar terms when stronger product-specific tags are available.

- Do not remove a strong existing tag merely to replace it with a different wording that provides no real improvement.

- Prefer a small set of strong, specific tags over a large set containing weak, repetitive or marginally useful tags.

- Aim for about 3 to 6 strong tags when the supplied PRODUCT FACTS support that many. Fewer tags are acceptable when there is not enough reliable information. Never invent tags simply to reach a target number.

PROMPT;
	}




	/**
	 * Build user-specific generation instructions.
	 *
	 * @param array<int,string> $selectedFields
	 */
	private function buildUserInstructionsLayer(
		array $selectedFields,
		string $language,
		string $toneOverride,
		string $additionalInstructions
	): string {

		$instructions = [];

		$instructions[] =
			'Requested fields: ' .
			implode(', ', $selectedFields);

		$language = trim($language);

		if ('' !== $language) {
			$instructions[] =
				'Language: ' . $language;
		}

		$toneOverride = trim($toneOverride);

		if ('' !== $toneOverride) {
			$instructions[] =
				'Tone override: ' . $toneOverride;
		}

		$additionalInstructions =
			trim($additionalInstructions);

		if ('' !== $additionalInstructions) {
			$instructions[] =
				'Additional instructions: ' .
				$additionalInstructions;
		}

		return sprintf(
			"=== USER INSTRUCTIONS ===\n%s",
			implode("\n", $instructions)
		);
	}


	/**
	 * Build the structured output requirements.
	 *
	 * The AI provider is asked for plain text generation,
	 * but the text itself must contain exactly one JSON
	 * object matching DetIt's output schema.
	 *
	 * @param array<int,string> $selectedFields
	 */
	private function buildOutputRequirementsLayer(
		array $selectedFields
	): string {

		$schema = OutputSchema::definition(
			$selectedFields
		);

		$schemaJson = wp_json_encode(
			$schema,
			JSON_PRETTY_PRINT |
				JSON_UNESCAPED_SLASHES |
				JSON_UNESCAPED_UNICODE
		);

		if (! is_string($schemaJson)) {
			throw new RuntimeException(
				'Unable to encode the DetIt output schema.'
			);
		}

		$fieldList = implode(
			', ',
			$selectedFields
		);

		return <<<PROMPT
=== OUTPUT REQUIREMENTS ===
Return exactly ONE valid JSON object and nothing else.

Use exactly these top-level fields:
{$fieldList}

Do not use Markdown code fences.
Do not add headings, explanations, commentary or text before or after the JSON.
Do not add any top-level fields that were not requested.

The JSON object must match this schema:

{$schemaJson}
PROMPT;
	}



	/**
	 * Convert structured data into a labelled JSON layer.
	 *
	 * @param string              $title
	 * @param array<string,mixed> $data
	 */
	private function buildJsonLayer(
		string $title,
		array $data
	): string {

		$json = wp_json_encode(
			$data,
			JSON_PRETTY_PRINT |
				JSON_UNESCAPED_SLASHES |
				JSON_UNESCAPED_UNICODE
		);

		if (! is_string($json)) {
			throw new RuntimeException(
				sprintf(
					'Unable to encode the %s prompt layer.',
					$title
				)
			);
		}

		return sprintf(
			"=== %s ===\n%s",
			$title,
			$json
		);
	}

	/**
	 * Normalize requested fields.
	 *
	 * @param array<int,mixed> $fields
	 *
	 * @return array<int,string>
	 */
	private function normalizeSelectedFields(
		array $fields
	): array {

		$normalized = [];

		foreach ($fields as $field) {

			if (! is_string($field)) {
				continue;
			}

			$field = trim($field);

			if ('' === $field) {
				continue;
			}

			$normalized[] = $field;
		}

		return array_values(
			array_unique($normalized)
		);
	}
}
