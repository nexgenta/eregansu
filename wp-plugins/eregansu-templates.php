<?php

/*
Plugin Name: Eregansu template compatibility
Author: Nexgenta
Author URI: http://github.com/nexgenta/eregansu
*/

function e($str)
{
	echo str_replace('&quot;', '&#39;', htmlspecialchars($str));
}

function _eregansu_e($str, $orig = null, $domain = null)
{
	global $_EREGANSU_TEMPLATE;
	
	if(defined('EREGANSU_TEMPLATE') || !empty($_EREGANSU_TEMPLATE))
	{
		return str_replace('&quot;', '&#39;', htmlspecialchars($str));
	}
	return $str;
}

add_filter('gettext', '_eregansu_e', 1, 2);
