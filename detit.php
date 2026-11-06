<?php

/**
 * Plugin Name: DetIt AI Content Generator for WooCommerce
 * Plugin URI: https://github.com/FranklynOkafor/detit-v2
 * Description: Generate and review WooCommerce product content with AI, and audit product content completeness.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Franklyn Okafor
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: detit-product-content-generator-for-woocommerce
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
define('DETIT_DB_VERSION', '1.6.0');
define('DETIT_FILE', __FILE__);
define('DETIT_PATH', plugin_dir_path(__FILE__));
define('DETIT_URL', plugin_dir_url(__FILE__));

/*
|--------------------------------------------------------------------------
| Composer
|--------------------------------------------------------------------------
*/

/*
 * DetIt has no third-party Composer packages at runtime. Developers may
 * use Composer locally, but merchants must not need Composer to activate
 * the published WordPress.org ZIP.
 *
 * Prefer Composer's PSR-4 loader when available in the development checkout.
 * Otherwise, load only DetIt's own namespaced classes from src/.
 */
$detitAutoload = DETIT_PATH . 'vendor/autoload.php';

if (is_readable($detitAutoload)) {
    require_once $detitAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'DetIt\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = DETIT_PATH . 'src/'
            . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    });
}

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
