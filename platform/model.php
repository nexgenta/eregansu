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
 * @year 2009
 * @include uses('model');
 * @since Available in Eregansu 1.0 and later. 
 */

uses('db');

/**
 * Base class for data models.
 *
 * The \class{Model} class is intended to be used as a base for classes which
 * provide interfaces to persistent storage, such as relational databases.
 */
class Model
{
	protected static $instances = array();
	public $db;
	public $dbIri;
	
	/**
	 * Obtains an instance of one of \class{Model}'s descendants.
	 *
	 * If \p{$args['class']} is not set, \m{getInstance} will immediately
	 * return \c{null}.
	 *
	 * Otherwise, an instance of the named class will be obtained, and its
	 * \m{__construct|constructor} will be invoked, passing \p{$args}.
	 *
	 * Descendants should override \m{getInstance} to set \p{$args['class']} to
	 * the name of the class if it's not set.
	 *
	 * Descendants should, if possible, ensure that \p{$args['db']} is set to
	 * a database connection IRI which can be passed to \m{DBCore::connect}.
	 *
	 * The combination of \p{$args['class']} and \p{$args['db']} are used to
	 * construct a key into the shared instance list. When a new instance is
	 * constructed, it is stored with this key in the list. If an entry with
	 * the key is already present, it will be returned and no new instance
	 * will be created.
	 *
	 * @type Model
	 * @param[in,optional] array $args Initialisation parameter array.
	 * @return On success, returns an instance of a descendant of \class{Model}.
	 */
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

	/**
	 * Construct an instance of \class{Model}.
	 *
	 * If \p{$args['db']} is a string of nonzero length, \P{$dbIri} will be
	 * set to its value, and \P{$db} will be assigned the result of passing
	 * it to \m{DBCore::connect} in order to establish a database connection.
	 *
	 * @param[in] array $args Initialisation parameters.
	 */
	public function __construct($args)
	{
		if(strlen($args['db']))
		{
			$this->dbIri = $args['db'];
			$this->db = DBCore::connect($args['db']);
		}
	}
}
