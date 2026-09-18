<?php

namespace DetIt\AI\Templates;

abstract class AbstractTemplate implements TemplateInterface {

	/**
	 * Safety rules shared by every built-in template.
	 *
	 * These rules are deliberately kept at the template layer so that
	 * every built-in template carries a minimum anti-hallucination
	 * contract before PromptBuilder is introduced.
	 *
	 * @return array<int, string>
	 */
	protected function get_safety_instructions(): array {
		return [
			'Use only facts supplied by the product fact sheet and other explicitly provided product data.',
			'Treat missing, empty, null or unknown product values as unavailable information.',
			'Do not invent product specifications, measurements, materials, features, compatibility, certifications, warranties or other factual claims.',
			'Omit unavailable product information instead of guessing or filling gaps.',
			'Do not turn assumptions or common characteristics of similar products into facts about this product.',
		];
	}

	/**
	 * Instructions specific to the concrete template.
	 *
	 * @return array<int, string>
	 */
	abstract protected function get_template_instructions(): array;

	/**
	 * Return shared safeguards followed by template-specific rules.
	 *
	 * @return array<int, string>
	 */
	final public function get_instructions(): array {
		return array_values(
			array_merge(
				$this->get_safety_instructions(),
				$this->get_template_instructions()
			)
		);
	}

	/**
	 * Convert the template into structured data.
	 *
	 * @return array<string, mixed>
	 */
	final public function to_array(): array {
		return [
			'slug'                 => $this->get_slug(),
			'name'                 => $this->get_name(),
			'description'          => $this->get_description(),
			'sections'             => $this->get_sections(),
			'attribute_priorities' => $this->get_attribute_priorities(),
			'instructions'         => $this->get_instructions(),
		];
	}
}