<?php

namespace DetIt;

use DetIt\Admin\Menu;
use DetIt\Storage\DatabaseInstaller;
use DetIt\Admin\BrandProfileSettingsPage;
use DetIt\Admin\ProductGenerateMetaBox;
use DetIt\REST\GenerateController;

if (! defined('ABSPATH')) {
	exit;
}

class Plugin
{
	public function init(): void
	{
		// Load translations.
		add_action('init', [$this, 'loadTextDomain']);

		// Admin features.
		$this->bootAdmin();

		// Placeholder boot methods for future stages.
		$this->bootRest();
		$this->bootDatabase();
		$this->bootWooCommerce();
		$this->bootAbilities();
		$this->bootQueue();
	}

	public function loadTextDomain(): void
	{
		load_plugin_textdomain(
			'detit-product-content-generator-for-woocommerce',
			false,
			dirname(plugin_basename(DETIT_FILE)) . '/languages'
		);
	}

	private function bootAdmin(): void
	{
		(new Menu())->registerHooks();
		if (is_admin()) {
			(new BrandProfileSettingsPage())->register();
		}
	}

	private function bootRest(): void
	{
		// Stage 38.
		(new GenerateController())->registerHooks();
	}

	private function bootDatabase(): void
	{
		// Stage 10.
		(new DatabaseInstaller())->maybeUpgrade();
	}

	private function bootWooCommerce(): void
	{
		// Stage 41.
		if (is_admin()) {
			(new ProductGenerateMetaBox())->registerHooks();
		}
	}

	private function bootAbilities(): void
	{
		// Stage 56.
	}

	private function bootQueue(): void
	{
		// Stage 73+.
	}
}
