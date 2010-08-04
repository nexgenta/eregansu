<?php

if(!defined('IDENTITY_IRI')) define('IDENTITY_IRI', null);

class IdentityModule extends Module
{
	public $moduleId = 'com.nexgenta.eregansu.identity';
	public $latestVersion = 0;
	
	public static function getInstance($args = null)
	{
		if(!isset($args['class'])) $args['class'] = 'IdentityModule';
		if(!isset($args['db'])) $args['db'] = IDENTITY_IRI;
		return parent::getInstance($args);
	}
	
	public function __construct($args)
	{
		if(!strncmp($args['db'], 'file:', 5) || !strncmp($args['db'], 'ldap:', 5))
		{
			$args['db'] = null;
		}
		parent::__construct($args);
	}
	
	public function dependencies()
	{
		$this->depend('com.nexgenta.eregansu.store');
	}
}
