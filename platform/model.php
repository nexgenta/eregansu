<?php

/* Eregansu: Data models
 *
 * Copyright 2009 Mo McRoberts.
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

/**
 * @framework Eregansu
 */

uses('db');

class Model
{
	protected static $instances = array();
	public $db;
	public $dbIri;
	
	public static function getInstance($args = null)
	{
		if(!isset($args['class'])) return null;
		$key = $args['class'] . (isset($args['db']) ? ':' . $args['db'] : null);
		$className = $args['class'];
		if(!isset($args['db'])) $args['db'] = null;
		if(!isset(self::$instances[$key]))
		{
			self::$instances[$key] = new $className($args);
		}
		return self::$instances[$key];
	}
	
	public function __construct($args)
	{
		if(strlen($args['db']))
		{
			$this->dbIri = $args['db'];
			$this->db = DBCore::connect($args['db']);
		}
	}
}
