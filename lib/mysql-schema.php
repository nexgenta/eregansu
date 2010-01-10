<?php

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
}

class MySQLTable extends DBTable
{
	protected $nativeCreateOptions = array(
		'engine' => 'ENGINE=InnoDB',
		'charset' => 'DEFAULT CHARSET=utf8',
		'collate' => 'DEFAULT COLLATE=utf8_general_ci',
	);
	
	protected function retrieve()
	{
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
				$info['flags'] |= DBTable::NOT_NULL;
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
				$spec = 'KEY';
				break;
			case DBIndex::INDEX:
				$spec = 'INDEX';
				break;
			default:
				trigger_error('MySQLTable: Unsupported index type ' . $type, E_USER_NOTICE);
				return false;
		}
		if($type != DBIndex::PRIMARY && strlen($name))
		{
			$spec .= ' "' . $name . '"';
		}
		$cols = array();
		foreach($columns as $c)
		{
			$cols[] = '"' . $c . '"';
		}
		$spec .= ' (' . implode(', ', $cols) . ')';
		$info = array(
			'name' => $name,
			'type' => $type,
			'columns' => $columns,
			'spec' => $spec,
		);
		if($name !== null)
		{
			$this->indices[$name] = $info;
		}
		else
		{
			$this->indices[] = $info;
		}
		return true;		
	}
	
}
