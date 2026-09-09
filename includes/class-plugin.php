<?php

if (! defined('ABSPATH')) {
	exit;
}

class DetIt_Plugin
{
	public function run()
	{
		load_plugin_textdomain(
			'detit-product-content-generator-for-woocommerce',
			false,
			dirname(plugin_basename(DETIT_FILE)) . '/languages'
		);

		// Future hooks go here.
	}
}