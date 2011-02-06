<?php

/* Eregansu: Identity database module
 *
 * Copyright 2010-2011 Mo McRoberts
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

if(!defined('IDENTITY_IRI')) define('IDENTITY_IRI', null);

class IdentityModule extends Module
{
	public $moduleId = 'com.nexgenta.eregansu.identity';
	public $latestVersion = 0;
	public $standalone = false;

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
