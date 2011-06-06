<?php

/* Eregansu: Auto-configuring database schema modules
 *
 * Copyright 2011 Mo McRoberts.
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

/* A “module” is essentially a class which, rather than
 * being responsible for storing and retrieving data in a
 * database, instead is concerned with managing the
 * structure of the database.
 *
 * Updates to the database schema are versioned, with the
 * module class specifying the current latest version number
 * and the incremental steps in how to get there.
 *
 * The implementation below does the heavy lifting of
 * comparing the version number in the database itself with
 * the module’s version number and invoking the descendant
 * schema update code in order to perform the necessary
 * incremental updates.
 */

require_once(dirname(__FILE__) . '/model.php');

abstract class Module extends Model
{
	/* The current latest version number for this module. If zero,
	 * no updates will be performed.
	 */
	public $latestVersion = 0;
	/* The module identifier: a reverse-DNS-style identifier for
	 * the module. For example, com.example.mymodule
	 */
	public $moduleId = null;
	/* The SetupCli instance calling us */
	protected $cli;
	/* Can we have our own database (true), or always piggyback? (false) */
	public $standalone = true;
	/* A reference to the database schema instance */
	protected $schema = null;
	
	public static function getInstance($args = null)
	{
		return parent::getInstance($args);
	}
	
	public function __construct($args)
	{
		if(isset($args['cli']))
		{
			$this->cli = $args['cli'];
		}
		parent::__construct($args);
	}
	
	/* Compare the stored version number in the database (if any)
	 * with $this->latestVersion. Cycle from the former to the
	 * latter, calling $this->updateSchema() as required.
	 */
	public function setup()
	{
		$this->dependencies();
		if(!$this->db)
		{
			if($this->standalone)
			{
				echo "Warning: skipping setup of " . $this->moduleId . " because it has no database\n";
			}
			return true;
		}
		$this->schema = $this->db->schema;
		$currentVersion = $this->moduleVersion();
		while($currentVersion < $this->latestVersion)
		{
			echo " -> Updating " . $this->moduleId . " from " . $currentVersion . " to " . ($currentVersion + 1) . "\n";
			do
			{
				$this->db->begin();
				$currentVersion = $this->moduleVersion();
				if($currentVersion >= $this->latestVersion)
				{
					$this->db->rollback();
					$this->schema = null;
					return true;
				}
				$result = $this->updateSchema($currentVersion + 1);
				if($result == false)
				{
					echo "*** Update of " . $this->moduleId . " from " . $currentVersion . " to " . ($currentVersion + 1) . " failed\n";
					$this->schema = null;
					return false;
				}
				if(is_string($result))
				{
					$comment = $result;
				}
				else
				{
					$comment = null;
				}
				$this->schema->setModuleVersion($this->moduleId, $currentVersion + 1, $comment);
			}
			while(!$this->db->commit());
			$currentVersion++;
		}
		$this->schema = null;
		return true;
	}

	/* Retrieve the version number of a specified module */
	protected function moduleVersion($moduleId = null)
	{
		if(!strlen($moduleId))
		{
			$moduleId = $this->moduleId;
		}
		return $this->schema->moduleVersion($moduleId);
	}
	
	/* Retrieve a database table instance */
	protected function table($name)
	{
		return $this->schema->table($name);
	}
	
	/* Create or retrieve a database table instance */
	protected function tableWithOptions($name, $options)
	{
		return $this->schema->tableWithOptions($name, $options);
	}
	
	/* Drop a database table */
	protected function dropTable($name)
	{
		return $this->schema->dropTable($name);
	}

	/* Perform a single incremental schema update. Return true
	 * if successful, or false if an unrecoverable error occurs
	 * which should abort the update process.
	 *
	 * The initial version is 1 (i.e., $targetVersion == 1) and
	 * will increment by one up to $this->latestVersion.
	 *
	 * (In other words, if $this->latestVersion is zero, this
	 * method will never be called).
	 */
	public function updateSchema($targetVersion)
	{
	
	}
	
	/* Override dependencies() to call $this->depend() for each other
	 * module that this depends upon.
	 */
	protected function dependencies()
	{
	}
	
	protected function depend($id, $iri = null, $info = null)
	{
		if(!is_object($this->cli))
		{
			echo "*** Cannot recurse into dependent module $id because " . get_class($this) . " is being invoked outside of the setup command-line utility\n";
			exit(1);
		}
		if($iri === null)
		{
			$iri = $this->dbIri;
		}
		$this->cli->depend($id, $iri, $info);
	}
}
