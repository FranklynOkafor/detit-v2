<?php

namespace DetIt\AI\Templates;

final class GeneralTemplate extends AbstractTemplate {

	public function get_slug(): string {
		return 'general';
	}

	public function get_name(): string {
		return 'General';
	}

	public function get_description(): string {
		return 'A flexible product content template suitable for most product categories.';
	}

	public function get_sections(): array {
		return [
			'product_overview',
			'key_features',
			'benefits',
			'use_cases',
			'important_details',
			'call_to_action',
		];
	}

	public function get_attribute_priorities(): array {
		return [
			'product_name',
			'product_type',
			'category',
			'key_features',
			'material',
			'colour',
			'dimensions',
			'capacity',
			'usage',
			'care_instructions',
		];
	}

	protected function get_template_instructions(): array {
		return [
			'Explain the product clearly to a general online-shopping audience.',
			'Prioritise the most useful verified product features and customer benefits.',
			'Include dimensions, capacity, materials, colours and care information only if they are known.',
			'Describe realistic use cases only when they are supported by the known product facts.',
			'Keep factual product details separate from persuasive marketing language.',
		];
	}
}