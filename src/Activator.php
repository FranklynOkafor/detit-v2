<?php
namespace DetIt;

use DetIt\Storage\DatabaseInstaller;

if (! defined('ABSPATH')) {
    exit;
}

class Activator
{
	public static function activate(): void
	{
		(new DatabaseInstaller())->install();

		flush_rewrite_rules();
	}
}