<?php
namespace DetIt;

if (! defined('ABSPATH')) {
	exit;
}

class Plugin
{
	public function init(): void
	{
		// Admin features
		add_action('admin_init', [$this, 'bootAdmin']);

		// Placeholder boot methods for future stages.
		$this->bootRest();
		$this->bootDatabase();
		$this->bootWooCommerce();
		$this->bootAbilities();
		$this->bootQueue();
	}

	private function bootAdmin(): void
	{
		// Stage 9 will add the admin menu.
	}

	private function bootRest(): void
	{
		// Stage 38.
	}

	private function bootDatabase(): void
	{
		// Stage 10.
	}

	private function bootWooCommerce(): void
	{
		// Future WooCommerce integrations.
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