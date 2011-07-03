<?php

/* Copyright 2010 Mo McRoberts.
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
	const BOOLEAN = 9;
	const UUID = 10;
	const TEXT = 11;
	const BLOB = 12;
	const BINARY = 13;
	const VARBINARY = 14;
	const TIME = 15;
	const DECIMAL = 16;
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
				require_once(dirname(__FILE__) . '/db/mysql-schema.php');
				return new MySQLSchema($connection);
			case 'sqlite3':
				require_once(dirname(__FILE__) . '/db/sqlite3-schema.php');
				return new SQLite3Schema($connection);
		}
		return null;
	}
	
	public function __construct($connection)
	{
		$this->db = $connection;
	}
	
	/* Drop a table */
	public function dropTable($name)
	{
		if(!($t = $this->tableWithOptions($name, DBTable::DROP)))
		{
			return false;
		}
		return $t->apply();
	}

	/* Rename a table */
	public function renameTable($oldName, $newName)
	{
		return $this->db->exec('ALTER TABLE {' . $oldName . '} RENAME TO {' . $newName . '}');
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
	const CREATE_NEVER = 0;
	const CREATE_IF_NEEDED = 1; /* Create it if it doesn't exist; no-op otherwise */
	const CREATE_ALWAYS = 2; /* Always create, dropping if necessary */
	const DROP = 3; /* Drop the table */

	public $schema;
	public $name;
	public $options;

	protected $exists;
	protected $columns = array();
	protected $indices = array();
	protected $changes = array();
	protected $nativeCreateOptions = array();
	
	public function __construct($schema, $name, $options)
	{
		$this->schema = $schema;
		$this->name = $name;
		$this->options = $options;
		
		if($options == self::EXISTING || $options == self::CREATE_IF_NEEDED)
		{
			$this->retrieve();
		}
	}
	
	public function apply()
	{
		if($this->options == self::DROP)
		{
			return $this->dropTable();
		}
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
	
	public function columns()
	{
		return $this->columns;
	}
	
	public function columnWithSpec($name, $type, $sizeValues, $flags = null, $defaultValue = null, $comment = null)
	{
		if($flags == null) $flags = DBCol::NULLS;
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
				break;
			case DBType::DATE:
			case DBType::DATETIME:
		    case DBType::TIME:
			case DBType::UUID:
			case DBType::BOOL:
			case DBType::SERIAL:
			case DBType::INT:
			case DBType::TEXT:
				$info['sizeValues'] = null;
				break;
			case DBType::DECIMAL:
				if(!is_array($info['sizeValues']) || count($info['sizeValues']) != 2)
				{
					trigger_error('DBTable::columnWithSpec: a two-entry array must be specified as the size for a DECIMAL column', E_USER_NOTICE);
					return false;
				}
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
	
	public function indexWithSpec($name, $type, $column /* ... */)
	{
		$columns = func_get_args();
		array_shift($columns);
		array_shift($columns);
		switch($type)
		{
			case DBIndex::PRIMARY:
				$spec = 'PRIMARY KEY';
				$name = 'PRIMARY';
				break;
			case DBIndex::UNIQUE:
				$spec = 'UNIQUE KEY';
				break;
			case DBIndex::INDEX:
				$spec = 'INDEX';
				break;
			default:
				trigger_error('DBTable: Unsupported index type ' . $type, E_USER_NOTICE);
				return false;
		}
		if(!strlen($name))
		{
			$name = implode('_', $columns);
		}
		$info = array(
			'name' => $name,
			'type' => $type,
			'columns' => $columns,
		);
		if(!($this->nativeIndexSpec($info)))
		{
			return false;
		}
		if($this->options == self::EXISTING)
		{		
			if(isset($this->indices[$info['name']]))
			{
				$this->dropIndex($info['name']);
			}
			$this->changes[] = $this->addIndex($info);
		}
		$this->indices[$info['name']] = $info;
		return true;
	}

	
	public function indices()
	{
		return $this->indices;
	}
	
	/* Drop an index from a table; can be overridden by descendants */
	public function dropIndex($name)
	{
		if(!strlen($name)) $name = 'PRIMARY';
		if(isset($this->indices[$name]))
		{
			unset($this->indices[$name]);			
			if($this->options == self::EXISTING)
			{		
				if($name === null || !strcasecmp($name, 'PRIMARY'))
				{
					$this->changes[] = 'ALTER TABLE {' . $this->name . '} DROP PRIMARY KEY';
				}
				else
				{
					$this->changes[] = 'ALTER TABLE {' . $this->name . '} DROP INDEX "' . $name . '"';
				}
			}
		}
	}
	
	/* Drop a column from a table */
	public function dropColumn($name)
	{
		if(isset($this->columns[$name]))
		{
			unset($this->indices[$name]);			
			if($this->options == self::EXISTING)
			{		
				$this->changes[] = 'ALTER TABLE {' . $this->name . '} DROP COLUMN "' . $name . '"';
			}
		}
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
	
	abstract protected function nativeIndexSpec(&$info);
	
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
	
	/* Return a string containing an ALTER TABLE statement for adding
	 * a new index.
	 */
	protected function addIndex($info)
	{
		return 'ALTER TABLE {' . $this->name . '} ADD ' . $info['spec'];
	}
	
	/* Execute a DROP TABLE statement */
	protected function dropTable()
	{
		$drop = 'DROP TABLE IF EXISTS {' . $this->name . '}';
		return $this->schema->db->exec($drop);
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
			if($this->exists)
			{
				return true;
			}
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
			if($index['type'] == DBIndex::PRIMARY)
			{
				$cl[] = $index['spec'];
			}
		}
		$create .= "\n    " . implode(",\n    ", $cl) . "\n) " . $this->nativeCreateOptions();
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
		foreach($this->indices as $index)
		{
			if($index['type'] == DBIndex::PRIMARY)
			{
				continue;
			}
			do
			{
				$this->schema->db->begin();
				$this->schema->db->exec($index['fullspec']);
			}
			while(!$this->schema->db->commit());
		}
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
				if(defined('DBSCHEMA_DEBUG') && DBSCHEMA_DEBUG)
				{
					echo "[$change]\n";
				}
				$this->schema->db->exec($change);
			}
		}
		while(!$this->schema->db->commit());
		return true;
	}
	
	protected function nativeCreateOptions()
	{
		$list = array();
		foreach($this->nativeCreateOptions as $name => $value)
		{
			$list[] = $name . ' ' . $value;
		}
		return implode(' ', $list);
	}
	
}
