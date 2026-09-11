<?php
namespace DetIt;

if (! defined('ABSPATH')) {
	exit;
}

class Requirements
{
	public static function check()
	{
		if (version_compare(PHP_VERSION, '8.1', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>DetIt</strong> requires PHP 8.1 or higher.</p></div>';
			});

			return false;
		}

		global $wp_version;

		if (version_compare($wp_version, '7.0', '<')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>DetIt</strong> requires WordPress 7.0 or higher.</p></div>';
			});

			return false;
		}

		if (! class_exists('WooCommerce')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>DetIt</strong> requires WooCommerce to be installed and activated.</p></div>';
			});

			return false;
		}

		return true;
	}
}