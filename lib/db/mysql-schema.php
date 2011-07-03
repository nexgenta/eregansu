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

require_once(dirname(__FILE__) . '/dbschema.php');

class MySQLSchema extends DBSchema
{
	protected $tableClass = 'MySQLTable';
	
	public function moduleVersion($moduleId)
	{
		try
		{
			$ver = $this->db->value('SELECT "version" FROM {_version} WHERE "ident" = ?', $moduleId);
		}
		catch(Exception $e)
		{
			$this->db->exec('CREATE TABLE IF NOT EXISTS {_version} ( ' .
				' "ident" VARCHAR(64) NOT NULL COMMENT \'Module identifier\', ' .
				' "version" BIGINT UNSIGNED NOT NULL COMMENT \'Current schema version\', ' .
				' "updated" DATETIME NOT NULL COMMENT \'Timestamp of the last schema update\', ' .
				' "comment" TEXT DEFAULT NULL COMMENT \'Description of the last update\', ' .
				' PRIMARY KEY ("ident") ' .
				') ENGINE=InnoDB DEFAULT CHARSET=utf8 DEFAULT COLLATE=utf8_general_ci');
			$ver = null;
		}
		if($ver === null)
		{
			do
			{
				$this->db->begin();
				$ver = $this->db->value('SELECT "version" FROM {_version} WHERE "ident" = ?', $moduleId);
				if($ver !== null)
				{
					$this->db->rollback();
					break;
				}
				$this->db->insert('_version', array(
					'ident' => $moduleId,
					'version' => 0,
					'@updated' => $this->db->now(),
					'comment' => null,
				));
			}
			while(!$this->db->commit());
			$ver = 0;
		}
		return $ver;
	}
	
	/* Return the list of databases accessible by this connection */
	public function databases()
	{
		$list = array();
		$rows = $this->db->rows('SHOW DATABASES');
		foreach($rows as $r)
		{
			$list[] = $r['Database'];
		}
		sort($list);
		return $list;
	}
	
	/* Return the list of database schema for a database */
	public function schemata($dbName)
	{
		return null;
	}
	
	/* Return the list of tables in a database (within a schema, if supported) */
	public function tables($dbName, $schemaName)
	{
		$list = array();
		$rows = $this->db->rows('SHOW FULL TABLES FROM "' . $dbName . '" WHERE "Table_type" = ?', 'BASE TABLE');
		foreach($rows as $r)
		{
			$r = array_values($r);
			$list[] = $r[0];
		}
		sort($list);
		return $list;
	}

	/* Return the list of views in a database (within a schema, if supported) */
	public function views($dbName, $schemaName)
	{
		return $this->db->column('SHOW FULL TABLES FROM "' . $dbName . '" WHERE "Table_type" = ?', 'VIEW');
	}
}

class MySQLTable extends DBTable
{
	protected $nativeCreateOptions = array(
		'ENGINE' => 'InnoDB',
		'DEFAULT COLLATE' => 'utf8_general_ci',
	);
	
	protected function retrieve()
	{		
		$catalog = null;
		$table = $this->schema->db->row('SELECT * FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ?', $this->schema->db->dbName, $this->name);
		if(!$table)
		{
			$catalog = 'def';
			$table = $this->schema->db->row('SELECT * FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_CATALOG" = ? AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ?', $catalog, $this->schema->db->dbName, $this->name);
		}
		if(!is_array($table))
		{
			return;
		}
		$this->exists = true;
		$this->nativeCreateOptions['ENGINE'] = $table['ENGINE'];
		$this->nativeCreateOptions['DEFAULT COLLATE'] = $table['TABLE_COLLATION'];
		$this->nativeCreateOptions['COMMENT'] = $table['TABLE_COMMENT'];
		if($catalog !== null)
		{
			$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "TABLE_CATALOG" = ? AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "ORDINAL_POSITION" ASC', $catalog, $this->schema->db->dbName, $this->name);
		}
		else
		{
			$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "ORDINAL_POSITION" ASC', $this->schema->db->dbName, $this->name);
		}
		foreach($info as $field)
		{
			$this->columns[$field['COLUMN_NAME']] = $this->columnFromNative($field);
		}
		if($catalog !== null)
		{
			$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."STATISTICS" WHERE "TABLE_CATALOG" = ? AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "INDEX_NAME", "SEQ_IN_INDEX" ASC', $catalog, $this->schema->db->dbName, $this->name);
		}
		else
		{
			$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."STATISTICS" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "INDEX_NAME", "SEQ_IN_INDEX" ASC', $this->schema->db->dbName, $this->name);
		}
		$this->indices = array();
		foreach($info as $index)
		{
			$this->indices[$index['INDEX_NAME']] = $this->indexFromNative($index);
		}
	}
	
	protected function nativeColumnSpec(&$info)
	{
		$spec = '"' . $info['name'] . '" ';
		switch($info['type'])
		{
			case DBType::CHAR:
				$spec .= 'CHAR(' . $info['sizeValues'] . ')';
				break;
			case DBType::INT:
				$spec .= ($info['flags'] & DBCol::BIG ? 'BIGINT' : 'INT');
				if($info['flags'] & DBCol::UNSIGNED)
				{
					$spec .= ' UNSIGNED';
				}
				break;
			case DBType::VARCHAR:
				$spec .= 'VARCHAR(' . $info['sizeValues'] . ')';
				break;
			case DBType::DATE:
				$spec .= 'DATE';
				break;
			case DBType::DATETIME:
				$spec .= 'DATETIME';
				break;
  		    case DBType::TIME:
				$spec .= 'TIME';
				break;
			case DBType::ENUM:
				$list = array();
				foreach($info['sizeValues'] as $v)
				{
					$list[] = $this->schema->db->quote($v);
				}
				$spec .= 'ENUM(' . implode(', ', $list) . ')';
				break;
			case DBType::SET:
				$list = array();
				foreach($info['sizeValues'] as $v)
				{
					$list[] = $this->schema->db->quote($v);
				}
				$spec .=  'SET(' . implode(', ', $list) . ')';
				break;
			case DBType::SERIAL:
				$spec .= 'BIGINT UNSIGNED';
				$info['flags'] |= DBCol::NOT_NULL;
				break;
			case DBType::BOOL:
				$spec .= 'ENUM(\'N\',\'Y\')';
				break;
			case DBType::UUID:
				$spec .= 'VARCHAR(36)';
				break;
			case DBType::TEXT:
				$spec .=  ($info['flags'] & DBCol::BIG ? 'LONGTEXT' : 'TEXT');
				break;
			case DBType::DECIMAL:
				$spec .= 'DECIMAL(' . $info['sizeValues'][0] . ',' . $info['sizeValues'][1] . ')';
				break;
			default:
				trigger_error('MySQLTable: Unsupported column type ' . $info['type'], E_USER_NOTICE);
				return false;
		}
		if($info['flags'] & DBCol::NOT_NULL)
		{
			$spec .= ' NOT NULL';
		}
		if($info['type'] == DBType::SERIAL)
		{
			$spec .= ' AUTO_INCREMENT';
		}
		if($info['default'] !== null)
		{
			$spec .= ' DEFAULT ' . $this->schema->db->quote($info['default']);
		}
		if($info['comment'] !== null)
		{
				$spec .= ' COMMENT ' . $this->schema->db->quote($info['comment']);
		}
		$info['spec'] = $spec;
		return true;
	}
	
	protected function nativeIndexSpec(&$info)
	{
		$cols = array();
		foreach($info['columns'] as $c)
		{
			$cols[] = '"' . $c . '"';
		}
		if($info['type'] != DBIndex::PRIMARY && !strcasecmp($info['name'], 'primary'))
		{
			trigger_error('MySQLTable: Only a primary key may be named "PRIMARY"', E_USER_NOTICE);
			return false;
		}
		switch($info['type'])
		{
			case DBIndex::PRIMARY:
				$info['spec'] = 'PRIMARY KEY (' . implode(', ', $cols) . ')';
				$info['name'] = 'PRIMARY';
				break;
			case DBIndex::INDEX:
				$info['spec'] = 'INDEX "' . $info['name'] . '" (' . implode(', ', $cols) . ')';
				$info['fullspec'] = 'CREATE INDEX "' . $this->name . '_' . $info['name'] . '" ON {' . $this->name . '} (' . implode(', ', $cols) . ')';
				break;
			case DBIndex::UNIQUE:
				$info['spec'] = 'UNIQUE KEY "' . $info['name'] . '" (' . implode(', ', $cols) . ')';
				$info['fullspec'] = 'CREATE UNIQUE INDEX "' . $this->name . '_' .  $info['name'] . '" ON {' . $this->name . '} (' . implode(', ', $cols) . ')';
				break;
			default:
				trigger_error('MySQLTable: Unsupported index type ' . $info['type'], E_USER_NOTICE);
				return false;
		}
		return true;
	}
	
	protected function columnFromNative($native)
	{
		$info = array(
			'name' => $native['COLUMN_NAME'],
			'type' => null,
			'sizeValues' => null,
			'flags' => ($native['IS_NULLABLE'] == 'YES' ? DBCol::NULLS : DBCol::NOT_NULL),
			'default' => $native['COLUMN_DEFAULT'],
			'comment' => $native['COLUMN_COMMENT'],
			'options' => array(),
			'spec' => '"' . $native['COLUMN_NAME'] . '" ' . $native['COLUMN_TYPE'],
		);
		$nt = explode(' ', strtolower($native['COLUMN_TYPE']));
		switch(strtolower($native['DATA_TYPE']))
		{
			case 'char':
				$info['type'] = DBType::CHAR;
				$info['sizeValues'] = $native['CHARACTER_MAXIMUM_LENGTH'];
				break;
			case 'varchar':
				$info['type'] = DBType::VARCHAR;
				$info['sizeValues'] = $native['CHARACTER_MAXIMUM_LENGTH'];
				break;
			case 'bigint':
				$info['flags'] |= DBCol::BIG;
			case 'int':
			case 'mediumint':
			case 'smallint':
			case 'tinyint':
				$info['type'] = DBType::INT;
				break;
			case 'longtext':
				$info['flags'] |= DBCol::BIG;
			case 'mediumtext':
			case 'text':
				$info['type'] = DBType::TEXT;
				break;
			case 'date':
				$info['type'] = DBType::DATE;
				break;
			case 'datetime':
			case 'timestamp':
				$info['type'] = DBType::DATETIME;
				break;
			case 'enum':
				$info['type'] = DBType::ENUM;
				break;
			case 'set':
				$info['type'] = DBType::SET;
				break;
			case 'longblob':
				$info['flags'] |= DBCol::BIG;
			case 'blob':
			case 'tinyblob':
			case 'mediumblob':
				$info['type'] = DBType::BLOB;
				break;
			case 'time':
				$info['type'] = DBType::TIME;
				break;
			case 'binary':
				$info['type'] = DBType::BINARY;
				break;
			case 'varbinary':
				$info['type'] = DBType::VARBINARY;
				break;
		}
		switch(strtolower($native['DATA_TYPE']))
		{
			case 'char':
			case 'varchar':
			case 'text':
			case 'longtext':
				$info['options']['CHARACTER SET'] = $native['CHARACTER_SET_NAME'];
				$info['options']['COLLATE'] = $native['COLLATION_NAME'];
				$info['spec'] .= ' CHARACTER SET ' . $native['CHARACTER_SET_NAME'];
				$info['spec'] .= ' COLLATE ' . $native['COLLATION_NAME'];
				break;
			case 'int':
			case 'bigint':
				if(in_array('unsigned', $nt))
				{
					$info['flags'] |= DBCol::UNSIGNED;
				}
				if($native['EXTRA'] == 'auto_increment')
				{
					$info['type'] = DBType::SERIAL;
				}
				break;
		}
		if($info['flags'] & DBCol::NOT_NULL)
		{
			$info['spec'] .= ' NOT NULL';
		}
		if($info['default'] !== null)
		{
			$info['spec'] .= ' DEFAULT ' . $this->schema->db->quote($info['default']);
		}
		if($info['comment'] !== null)
		{
			$info['spec'] .= ' COMMENT ' . $this->schema->db->quote($info['comment']);
		}
		return $info;
	}
	
	protected function indexFromNative($native)
	{
		$name = $native['INDEX_NAME'];
		if(isset($this->indices[$name]))
		{
			$info = $this->indices[$name];
		}
		else
		{
			$info = array(
				'name' => $native['INDEX_NAME'],
				'type' => null,
				'columns' => array(),
				'spec' => null,
			);
			if($info['name'] == 'PRIMARY')
			{
				$info['type'] = DBIndex::PRIMARY;
			}
			else if($native['NON_UNIQUE'])
			{
				$info['type'] = DBIndex::INDEX;				
			}
			else
			{
				$info['type'] = DBIndex::UNIQUE;
			}
		}
		$info['columns'][] = $native['COLUMN_NAME'];
		$this->nativeIndexSpec($info);		
		return $info;
	}
}
