<?php

namespace DetIt\AI\Templates;

final class FashionTemplate extends AbstractTemplate {

	public function get_slug(): string {
		return 'fashion';
	}

	public function get_name(): string {
		return 'Fashion';
	}

	public function get_description(): string {
		return 'A product content template for clothing, footwear, bags, accessories and related fashion products.';
	}

	public function get_sections(): array {
		return [
			'product_overview',
			'design_and_style',
			'material_and_construction',
			'fit_and_size',
			'key_features',
			'care_information',
			'call_to_action',
		];
	}

	public function get_attribute_priorities(): array {
		return [
			'product_name',
			'product_type',
			'material',
			'fabric',
			'colour',
			'pattern',
			'size',
			'fit',
			'closure',
			'design_features',
			'care_instructions',
		];
	}

	protected function get_template_instructions(): array {
		return [
			'Describe the verified appearance, design and construction of the fashion product clearly.',
			'Mention fabric or material only when it is explicitly known.',
			'Mention available colours and sizes only when they are present in the product facts.',
			'Do not infer fit, stretch, sizing accuracy, gender, fabric composition or body type suitability from the product image or category alone.',
			'Do not invent care instructions.',
			'Do not claim suitability for a particular occasion unless the known product information supports that claim.',
		];
	}
}