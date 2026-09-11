<?php
namespace DetIt;

if (! defined('ABSPATH')) {
	exit;
}

class Deactivator
{
	public static function deactivate()
	{
		flush_rewrite_rules();
	}
}