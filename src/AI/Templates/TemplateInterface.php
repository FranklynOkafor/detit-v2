<?php

namespace DetIt\AI\Templates;

interface TemplateInterface {

	/**
	 * Unique machine-readable template identifier.
	 */
	public function get_slug(): string;

	/**
	 * Human-readable template name.
	 */
	public function get_name(): string;

	/**
	 * Short explanation of the template.
	 */
	public function get_description(): string;

	/**
	 * Suggested content sections for this template.
	 *
	 * @return array<int, string>
	 */
	public function get_sections(): array;

	/**
	 * Product attributes that deserve extra attention.
	 *
	 * @return array<int, string>
	 */
	public function get_attribute_priorities(): array;

	/**
	 * Template-specific generation instructions.
	 *
	 * @return array<int, string>
	 */
	public function get_instructions(): array;

	/**
	 * Return the complete structured template.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}