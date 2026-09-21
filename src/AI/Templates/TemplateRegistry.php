<?php

namespace DetIt\AI\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TemplateRegistry {

	/**
	 * Registered templates indexed by slug.
	 *
	 * @var array<string, TemplateInterface>
	 */
	private array $templates = [];

	public function __construct() {
		$this->register( new GeneralTemplate() );
		$this->register( new FashionTemplate() );
		$this->register( new ElectronicsTemplate() );
	}

	/**
	 * Return all registered templates.
	 *
	 * @return array<string, TemplateInterface>
	 */
	public function all(): array {
		return $this->templates;
	}

	/**
	 * Retrieve a template by slug.
	 */
	public function get( string $slug ): ?TemplateInterface {
		$slug = strtolower( trim( $slug ) );

		return $this->templates[ $slug ] ?? null;
	}

	/**
	 * Check whether a template exists.
	 */
	public function has( string $slug ): bool {
		$slug = strtolower( trim( $slug ) );

		return isset( $this->templates[ $slug ] );
	}

	/**
	 * Register a template.
	 */
	private function register( TemplateInterface $template ): void {
		$slug = strtolower( trim( $template->get_slug() ) );

		if ( '' === $slug ) {
			return;
		}

		$this->templates[ $slug ] = $template;
	}
}