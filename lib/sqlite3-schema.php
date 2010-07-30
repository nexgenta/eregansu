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

require_once(dirname(__FILE__) . '/dbschema.php');

class SQLite3Schema extends DBSchema
{
	protected $tableClass = 'SQLite3Table';
	
	public function moduleVersion($moduleId)
	{
		try
		{
			$ver = $this->db->value('SELECT "version" FROM {_version} WHERE "ident" = ?', $moduleId);
		}
		catch(Exception $e)
		{
			$this->db->exec('CREATE TABLE IF NOT EXISTS {_version} ( ' .
				' "ident" VARCHAR(64) NOT NULL, ' .
				' "version" UNSIGNED BIG INT NOT NULL, ' .
				' "updated" DATETIME NOT NULL, ' .
				' "comment" TEXT DEFAULT NULL, ' .
				' PRIMARY KEY ("ident") ' .
				')');
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
		$list[] = $this->db->dbName;
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
		$rows = $this->db->rows('SELECT * FROM "sqlite_master" WHERE "type" = ?', 'table');
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
		$rows = $this->db->rows('SELECT * FROM "sqlite_master" WHERE "type" = ?', 'view');
	}
	
}

class SQLite3Table extends DBTable
{
	protected $nativeCreateOptions = array(
	);
	
	protected function retrieve()
	{
		$table = $this->schema->db->row('SELECT * FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ?', $this->schema->db->dbName, $this->name);
		$this->nativeCreateOptions['COMMENT'] = $table['TABLE_COMMENT'];
		$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "ORDINAL_POSITION" ASC', $this->schema->db->dbName, $this->name);
		foreach($info as $field)
		{
			$this->columns[$field['COLUMN_NAME']] = $this->columnFromNative($field);
		}
		$info = $this->schema->db->rows('SELECT * FROM "INFORMATION_SCHEMA"."STATISTICS" WHERE "TABLE_CATALOG" IS NULL AND "TABLE_SCHEMA" = ? AND "TABLE_NAME" = ? ORDER BY "INDEX_NAME", "SEQ_IN_INDEX" ASC', $this->schema->db->dbName, $this->name);
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
			case DBType::ENUM:
				$list = array();
				$len = 1;
				foreach($info['sizeValues'] as $v)
				{
					if(strlen($v) > $len)
					{
						$len = strlen($v);
					}
				}
				$spec .= 'VARCHAR(' . $len . ')';
				break;
			case DBType::SET:
				$list = array();
				$len = strlen(implode(',', $info['sizeValues']));
				$spec .=  'VARCHAR(' . $len . ')';
				break;
			case DBType::SERIAL:
				$spec .= 'INTEGER';
				$info['flags'] |= DBCol::NOT_NULL;
				break;
			case DBType::BOOL:
				$spec .= 'CHAR(1)';
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
				trigger_error('SQLite3Table: Unsupported column type ' . $info['type'], E_USER_NOTICE);
				return false;
		}
		if($info['type'] == DBType::SERIAL)
		{
			$spec .= 'PRIMARY KEY AUTOINCREMENT';
		}
		else
		{
			if($info['flags'] & DBCol::NOT_NULL)
			{
				$spec .= ' NOT NULL';
			}
			if($info['default'] !== null)
			{
				$spec .= ' DEFAULT ' . $this->schema->db->quote($info['default']);
			}
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
			trigger_error('SQLite3Table: Only a primary key may be named "PRIMARY"', E_USER_NOTICE);
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
				$info['spec'] = 'UNIQUE INDEX "' . $info['name'] . '" (' . implode(', ', $cols) . ')';
				$info['fullspec'] = 'CREATE UNIQUE INDEX "' . $this->name . '_' . $info['name'] . '" ON {' . $this->name . '} (' . implode(', ', $cols) . ')';
				break;
			default:
				trigger_error('SQLite3Table: Unsupported index type ' . $info['type'], E_USER_NOTICE);
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
				'fullspec' => null,
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