<?php

if (! defined('ABSPATH')) {
	exit;
}

class DetIt_Activator
{
	public static function activate()
	{
		flush_rewrite_rules();
	}
}