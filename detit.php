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
define('DETIT_FILE', __FILE__);
define('DETIT_PATH', plugin_dir_path(__FILE__));
define('DETIT_URL', plugin_dir_url(__FILE__));

/*
|--------------------------------------------------------------------------
| Composer Autoloader
|--------------------------------------------------------------------------
*/

$autoload = DETIT_PATH . 'vendor/autoload.php';

if (file_exists($autoload)) {
	require_once $autoload;
}

/*
|--------------------------------------------------------------------------
| Load Core Classes
|--------------------------------------------------------------------------
*/

require_once DETIT_PATH . 'includes/class-requirements.php';
require_once DETIT_PATH . 'includes/class-activator.php';
require_once DETIT_PATH . 'includes/class-deactivator.php';
require_once DETIT_PATH . 'includes/class-plugin.php';

// require_once __DIR__ . '/includes/class-requirements.php';
// require_once __DIR__ . '/includes/class-activator.php';
// require_once __DIR__ . '/includes/class-deactivator.php';
// require_once __DIR__ . '/includes/class-plugin.php';


/*
|--------------------------------------------------------------------------
| Load Autoload
|--------------------------------------------------------------------------
*/


require_once __DIR__ . '/vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Activation & Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook(DETIT_FILE, ['DetIt_Activator', 'activate']);
register_deactivation_hook(DETIT_FILE, ['DetIt_Deactivator', 'deactivate']);

/*
|--------------------------------------------------------------------------
| Bootstrap Plugin
|--------------------------------------------------------------------------
*/

add_action('plugins_loaded', function () {

	if (! DetIt_Requirements::check()) {
		return;
	}

	$plugin = new DetIt_Plugin();
	$plugin->run();
});


// Tester


// $plugin = new \DetIt\Plugin();