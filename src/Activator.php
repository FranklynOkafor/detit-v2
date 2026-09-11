<?php
namespace DetIt;

if (! defined('ABSPATH')) {
	exit;
}

class Activator
{
	public static function activate()
	{
		flush_rewrite_rules();
	}
}