<?php

/* Copyright 2010 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

abstract class DBIndex
{
	const PRIMARY = 1;
	const UNIQUE = 2;
	const INDEX = 3;
}

abstract class DBType
{
	const CHAR = 1;
	const INT = 2;
	const VARCHAR = 3;
	const DATE = 4;
	const DATETIME = 5;
	const ENUM = 6;
	const SET = 7;
	const SERIAL = 8;
	const BOOL = 9;
	const UUID = 10;
	const TEXT = 11;
}

abstract class DBCol
{
	const NULLS = 0;
	const NOT_NULL = 1;
	const UNSIGNED = 2;
	const BIG = 4; /* Use largest available field width (e.g., BIGINT vs INT, LONGTEXT vs TEXT) */
}

abstract class DBSchema
{
	public $db; /* Associated database connection */
	
	protected $tableClass; /* Name of descendant of DBTable */
	
	public static function schemaForConnection($connection)
	{
		switch($connection->dbms)
		{
			case 'mysql':
				require_once(dirname(__FILE__) . '/mysql-schema.php');
				return new MySQLSchema($connection);
		}
		return null;
	}
	
	public function __construct($connection)
	{
		$this->db = $connection;
	}
	
	/* Return an existing table */
	public function table($name)
	{
		return $this->tableWithOptions($name, DBTable::EXISTING);
	}
	
	/* Return an existing table or create a new one */
	public function tableWithOptions($name, $options)
	{
		$c = $this->tableClass;
		if(!strlen($c))
		{
			trigger_error('Fatal error: DBSchema: {$this->tableClass} is undefined or empty', E_USER_ERROR);
			exit(1);
		}
		return new $c($this, $name, $options);
	}
	
	/* Return the version number of the specified module */
	abstract public function moduleVersion($moduleId);
	
	/* Update the stored version number of the specified module */
	public function setModuleVersion($moduleId, $newVersion, $comment = null)
	{
		$this->db->exec('UPDATE {_version} SET "version" = ?, "comment" = ?, "updated" = ' . $this->db->now() . ' WHERE "ident" = ?', $newVersion, $comment, $moduleId);
	}
}

abstract class DBTable
{
	/* Flags for DBSchema::tableWithOptions */
	const EXISTING = 0; /* Don't create the table: retrieve existing spec */
	const CREATE_IF_NEEDED = 1; /* Create it if it doesn't exist; no-op otherwise */
	const CREATE_ALWAYS = 2; /* Always create, dropping if necessary */
	
	public $schema;
	public $name;
	public $options;

	protected $columns = array();
	protected $indices = array();
	protected $changes = array();
	protected $nativeCreateOptions = array();
	
	public function __construct($schema, $name, $options)
	{
		$this->schema = $schema;
		$this->name = $name;
		$this->options = $options;
		
		if($options == self::EXISTING)
		{
			$this->retrieve();
		}
	}
	
	public function apply()
	{
		if($this->options == self::EXISTING)
		{
			if(count($this->changes))
			{
				return $this->applyChanges();
			}
			return true;
		}
		return $this->createTable();
	}
	
	public function column($name)
	{
		if(isset($this->columns[$name])) return $this->columns[$name];
		return null;
	}
	
	public function columnWithSpec($name, $type, $sizeValues, $flags = null, $defaultValue = null, $comment = null)
	{
		if($flags == null) $flags = self::NULLS;
		$info = array(
			'name' => $name,
			'type' => $type,
			'sizeValues' => $sizeValues,
			'flags' => $flags,
			'default' => $defaultValue,
			'comment' => $comment,
		);
		switch($type)
		{
			case DBType::CHAR:
				if(empty($info['sizeValues'])) $info['sizeValues'] = 1;
				if(!is_int($info['sizeValues']))
				{
					trigger_error('DBTable::columnWithSpec: the size of a CHAR column must be an integer or null', E_USER_NOTICE);
					return false;
				}
				break;
			case DBType::VARCHAR:
				if(!is_int($info['sizeValues']))
				{
					trigger_error('DBTable::columnWithSpec: the size of a VARCHAR column must be an integer', E_USER_NOTICE);
					return false;
				}
				break;
			case DBType::ENUM:
			case DBType::SET:
				if(!is_array($info['sizeValues']) || !count($info['sizeValues']))
				{
					trigger_error('DBTable::columnWithSpec: an array of values must be specified for ' . ($type == DBType::ENUM ? 'an ENUM' : 'a SET') . ' column', E_USER_NOTICE);
					return false;
				}
			case DBType::DATE:
			case DBType::DATETIME:
			case DBType::UUID:
			case DBType::BOOL:
			case DBType::SERIAL:
			case DBType::INT:
			case DBType::TEXT:
				$info['sizeValues'] = null;
				break;
			default:
				trigger_error('DBTable: Unsupported column type ' . $info['type'], E_USER_NOTICE);
				return false;
		}
		if(!$this->nativeColumnSpec($info))
		{
			return false;
		}
		if($this->options == self::EXISTING)
		{
			if(isset($this->columns[$name]))
			{
				$this->changes[] = $this->replaceColumn($info);
			}
			else
			{
				$this->changes[] = $this->addColumn($info);
			}
		}
		$this->columns[$name] = $info;
	}
	
	/* Query the database schema and populate $this->columns and
	 * $this->indices with the properties of the existing table.
	 */
	abstract protected function retrieve();
	
	/* Set $info['spec'] to be a native column specification string
	 * for the given specification parameters, suitable for use in
	 * CREATE TABLE and ALTER TABLE statements.
	 *
	 * e.g., "foo" VARCHAR(32) NOT NULL DEFAULT 'Bar' COMMENT 'A dummy column'
	 */
	abstract protected function nativeColumnSpec(&$info);
	
	abstract public function indexWithSpec($name, $type, $column /* ... */);
	
	/* Return a string containing an ALTER TABLE statement for replacing
	 * an existing column with a new specification.
	 *
	 * If the previous column name differs from the new one, then
	 * $info['previous'] will be set to the old name.
	 */
	protected function replaceColumn($info)
	{
		if(isset($info['previous']))
		{
			return 'ALTER TABLE {' . $this->name . '} CHANGE COLUMN "' . $info['previous'] . '" ' . $info['spec'];
		}
		return 'ALTER TABLE {' . $this->name . '} MODIFY COLUMN ' . $info['spec'];
	}
	
	/* Return a string containing an ALTER TABLE statement for adding
	 * a new column.
	 */
	protected function addColumn($info)
	{
		return 'ALTER TABLE {' . $this->name . '} ADD COLUMN ' . $info['spec'];
	}
	
	/* Execute a CREATE TABLE statement based on the content of
	 * $this->columns and $this->indicies.
	 */
	protected function createTable()
	{
		if(!count($this->columns))
		{
			trigger_error('DBTable: Cannot create a table with no defined columns', E_USER_NOTICE);
			return false;
		}
		$drop = 'DROP TABLE IF EXISTS {' . $this->name . '}';
		$cl = array();
		if($this->options == self::CREATE_IF_NEEDED)
		{
			$create = 'CREATE TABLE IF NOT EXISTS {' . $this->name . '} (';
		}
		else
		{
			$create = 'CREATE TABLE {' . $this->name . '} (';		
		}
		foreach($this->columns as $col)
		{
			$cl[] = $col['spec'];
		}
		foreach($this->indices as $index)
		{
			$cl[] = $index['spec'];
		}
		$create .= "\n    " . implode(",\n    ", $cl) . "\n) " . implode(' ', $this->nativeCreateOptions);
		do
		{
			$this->schema->db->begin();
			if($this->options == self::CREATE_ALWAYS)
			{
				$this->schema->db->exec($drop);
			}
			$this->schema->db->exec($create);		
		}
		while(!$this->schema->db->commit());
		return true;
	}
	
	/* Execute a sequence of ALTER TABLE statements contained within
	 * $this->changes
	 */
	protected function applyChanges()
	{
		do
		{
			$this->schema->db->begin();
			foreach($this->changes as $change)	
			{
				$this->schema->db->exec($change);
			}
		}
		while(!$this->db->commit());
		return true;
	}
	
}
