<?php

if (! defined('ABSPATH')) {
	exit;
}

class DetIt_Deactivator
{
	public static function deactivate()
	{
		flush_rewrite_rules();
	}
}