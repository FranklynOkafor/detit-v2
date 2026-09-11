<?php

/**
 * Plugin Name: DetIt AI Content Generator for WooCommerce
 * Plugin URI: https://github.com/Franklyn-Okafor/DetIt
 * Description: Generate SEO-optimized WooCommerce product titles, descriptions, tags, and metadata with AI.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Franklyn Okafor
 * License: GPL-2.0-or-later
 * Text Domain: detit-product-content-generator-for-woocommerce
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define('DETIT_VERSION', '2.0.0');
define('DETIT_DB_VERSION', '1.1.0');
define('DETIT_FILE', __FILE__);
define('DETIT_PATH', plugin_dir_path(__FILE__));
define('DETIT_URL', plugin_dir_url(__FILE__));

/*
|--------------------------------------------------------------------------
| Composer
|--------------------------------------------------------------------------
*/

require_once DETIT_PATH . 'vendor/autoload.php';

use DetIt\Activator;
use DetIt\Deactivator;
use DetIt\Plugin;
use DetIt\Requirements;

/*
|--------------------------------------------------------------------------
| Activation & Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook(DETIT_FILE, [Activator::class, 'activate']);
register_deactivation_hook(DETIT_FILE, [Deactivator::class, 'deactivate']);

/*
|--------------------------------------------------------------------------
| Bootstrap Plugin
| Wait till all the plugins are loaded (because of the dependencies)
|--------------------------------------------------------------------------
*/

add_action('plugins_loaded', static function (): void {

	if (! Requirements::check()) {
		return;
	}

	(new Plugin())->init();
}, 20);
