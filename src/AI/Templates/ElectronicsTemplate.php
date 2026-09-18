<?php

namespace DetIt\AI\Templates;

final class ElectronicsTemplate extends AbstractTemplate {

	public function get_slug(): string {
		return 'electronics';
	}

	public function get_name(): string {
		return 'Electronics';
	}

	public function get_description(): string {
		return 'A product content template for electrical, electronic and technology-related products.';
	}

	public function get_sections(): array {
		return [
			'product_overview',
			'key_features',
			'technical_details',
			'compatibility',
			'power_and_performance',
			'included_items',
			'usage_information',
			'call_to_action',
		];
	}

	public function get_attribute_priorities(): array {
		return [
			'product_name',
			'model',
			'product_type',
			'power',
			'voltage',
			'wattage',
			'battery',
			'capacity',
			'connectivity',
			'compatibility',
			'dimensions',
			'included_accessories',
			'warranty',
			'certifications',
		];
	}

	protected function get_template_instructions(): array {
		return [
			'Prioritise verified technical specifications when they are available.',
			'Mention model numbers, voltage, wattage, battery information and capacity only when explicitly known.',
			'Do not infer device compatibility from the product category or appearance.',
			'Do not invent performance figures, battery life, charging time, power ratings or connectivity standards.',
			'Do not claim certifications, safety approvals or regulatory compliance unless those facts are explicitly supplied.',
			'Do not claim that a warranty exists unless warranty information is present in the known product facts.',
			'Only list included accessories or package contents when they are explicitly known.',
		];
	}
}