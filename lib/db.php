<?php

/* Copyright 2009, 2010 Mo McRoberts.
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
 
uses('url');

class DBException extends Exception
{
	public $errMsg;
	public $query;
	public $code;
	
	public function __construct($errCode, $errMsg, $query)
	{
		$this->errMsg = $errMsg;
		$this->query = $query;
		if(strlen($query))
		{
			parent::__construct($errMsg . ' while executing: ' . $query, $errCode);
		}
		else
		{
			parent::__construct($errMsg, $errCode);
		}
	}
}

interface DataSet extends Iterator
{
}

/* Database errors relating to connection and configuration (rather than
 * malformed queries, data integrity, and so on. These exceptions may be
 * caught and considered transient in some circumstances, but should not
 * generally cause an automatic immediate retry.
 */
class DBSystemException extends DBException
{
}

/* Database errors relating to connections and authentication */
class DBNetworkException extends DBSystemException
{
}

/* Database errors relating to transient scenarios which caused the transaction
 * to be rolled back.
 */
class DBRollbackException extends DBException
{
}

interface IDBCore
{
	public function __construct($params);
	public function vquery($query, $params);
	public function query($query);
	public function exec($query);
	public function value($query);
	public function row($query);
	public function rows($query);
	public function insert($table, $kv);
	public function update($table, $kv, $clause);
	public function quoteObject($name);
	public function quoteObjectRef(&$name);
	public function quoteRef(&$value);
	public function quote($value);
	public function insertId();
	public function begin();
	public function rollback();
	public function commit();
}

abstract class DBCore implements IDBCore
{
	protected $rsClass;
	protected $params;
	protected $schema;
	protected $dbName;
	protected $schemaName;
	public $dbms = 'unknown';
	public $prefix = '';
	public $suffix = '';
	
	public static function connect($iristr)
	{
		$iri = self::parseIRI($iristr);
		switch($iri['scheme'])
		{
			case 'mysql':
				return new MySQL($iri);
			case 'ldap':
				require_once(dirname(__FILE__) . '/ldap.php');
				return new LDAP($iri);
			default:
				throw new DBException(0, 'Unsupported database connection scheme "' . $iri['scheme'] . '"', null);
		}
	}
	
	public static function parseIRI($iristr)
	{
		if(is_array($iristr))
		{
			$iri = $iristr;
		}
		else
		{
			$iri = URL::parse($iristr);
		}
		if(!isset($iri['user']))
		{
			$iri['user'] = null;
		}
		if(!isset($iri['pass']))
		{
			$iri['pass'] = null;
		}
		if(!isset($iri['path']))
		{
			$iri['path'] = null;
		}		
		if(!isset($iri['dbname']))
		{
			$iri['dbname'] = null;
			$x = explode('/', $iri['path']);
			foreach($x as $p)
			{
				if(strlen($p))
				{
					$iri['dbname'] = $p;
					break;
				}
			}
		}
		if(!isset($iri['scheme']))
		{
			/* XXX if $iristr is already an array, this will fail */
			throw new DBException(0, 'Connection IRI ' . $iristr . ' has no scheme', null);
			return;
		}
		$iri['options'] = array();
		if(isset($iri['query']) && strlen($iri['query']))
		{
			$q = explode(';', str_replace('&', ';', $iri['query']));
			foreach($q as $qv)
			{
				$kv = explode('=', $qv, 2);
				$iri['options'][urldecode($kv[0])] = urldecode($kv[1]);
			}
		}
		return $iri;	
	}
	
	public function __construct($params)
	{
		$this->params = $params;
		if(isset($this->params['options']['autoconnect']))
		{
			$this->params['options']['autoconnect'] = parse_bool($this->params['options']['autoconnect']);
		}
		else
		{
			$this->params['options']['autoconnect'] = true;
		}
		if(isset($this->params['options']['prefix']))
		{
			$this->prefix = $this->params['options']['prefix'];
		}
		if(isset($this->params['options']['suffix']))
		{
			$this->suffix = $this->params['options']['suffix'];
		}
		if($this->params['options']['autoconnect'])
		{
			$this->autoconnect();
		}
	}
	
	public function begin()
	{
		$this->execute('START TRANSACTION');
	}
	
	public function rollback()
	{
		$this->execute('ROLLBACK');
	}
	
	public function vquery($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql);
	}
	
	public function exec($query)
	{
		$params = func_get_args();
		array_shift($params);
		if($this->vquery($query, $params))
		{
			return true;
		}
		return false;
	}

	public function vexec($query, $params)
	{
		if($this->vquery($query, $params))
		{
			return true;
		}
		return false;
	}

	/* Invoke $function within a transaction which will be automatically re-tried
	 * if necessary.
	 */
	public function perform($function, $data = null, $maxRetries = 10)
	{
		$count = 0;
		while($maxRetries < 0 || $count < $maxRetries)
		{
			try
			{
				$this->begin();
				if(call_user_func($function, $this, $data))
				{
					if($this->commit())
					{
						return true;
					}
					continue;
				}
				$this->rollback();
				return false;
			}
			catch(DBRollbackException $e)
			{
				$count++;
			}
		}
		throw new DBRollbackException(0, 'Repeatedly failed to perform transaction (retried ' . $maxRetries . ' times)');
	}

	/* $rs = $inst->query('SELECT * FROM `sometable` WHERE `field` = ? AND `otherfield` = ?', $something, 27); */
	public function query($query)
	{
		$params = func_get_args();
		array_shift($params);
		if(($r =  $this->vquery($query, $params)))
		{
			return new $this->rsClass($this, $r, $query, $params);
		}
		return null;
	}

	public function queryArray($query, $params)
	{
		if(($r =  $this->vquery($query, $params)))
		{
			return new $this->rsClass($this, $r, $query, $params);
		}
		return null;
	}

	public function value($query)
	{
		$row = null;
		$params = func_get_args();
		array_shift($params);
		if(($r = $this->vquery($query, $params)))
		{
			$rs = new $this->rsClass($this, $r, $query, $params);
			$row = $rs->next();
			$rs = null;
			if($row)
			{
				foreach($row as $v)
				{
					return $v;
				}
			}
		}
		return null;
	}

	public function valueArray($query, $params)
	{
		$row = null;
		if(($r = $this->vquery($query, $params)))
		{
			$rs = new $this->rsClass($this, $r, $query, $params);
			$row = $rs->next();
			$rs = null;
			if($row)
			{
				foreach($row as $v)
				{
					return $v;
				}
			}
		}
		return null;
	}

	public function row($query)
	{
		$row = null;
		$params = func_get_args();
		array_shift($params);
		if(($r =  $this->vquery($query, $params)))
		{
			$rs = new $this->rsClass($this, $r, $query, $params);
			$row = $rs->next();
			$rs = null;
		}
		return $row;
	}

	public function rowArray($query, $params)
	{
		$row = null;
		if(($r =  $this->vquery($query, $params)))
		{
			$rs = new $this->rsClass($this, $r, $query, $params);
			$row = $rs->next();
			$rs = null;
		}
		return $row;
	}

	public function rows($query)
	{
		$rows = null;
		$params = func_get_args();
		array_shift($params);
		if(($r =  $this->vquery($query, $params)))
		{
			$rows = array();
			$rs = new $this->rsClass($this, $r, $query, $params);
			while(($row = $rs->next()))
			{
				$rows[] = $row;
			}
			$rs = null;
		}
		return $rows;
	}

	public function rowsArray($query, $params)
	{
		$rows = null;
		if(($r =  $this->vquery($query, $params)))
		{
			$rows = array();
			$rs = new $this->rsClass($this, $r, $query, $params);
			while(($row = $rs->next()))
			{
				$rows[] = $row;
			}
			$rs = null;
		}
		return $rows;
	}
	
	protected function reportError($errcode, $errmsg, $sqlString, $class = 'DBException')
	{
		throw new $class($errcode, $errmsg, $sqlString);
	}
	
	public function insert($table, $kv)
	{
		$keys = array_keys($kv);
		$klist = array();
		foreach($keys as $k)
		{
			if(substr($k, 0, 1) == '@')
			{
				$values[] = $kv[$k];
				$klist[] = substr($k, 1);
			}
			else
			{
				$klist[] = $this->quoteObject($k);
				$values[] = $this->quote($kv[$k]);
			}
		}
		$sql = 'INSERT INTO ' . $this->quoteTable($table) . ' (' . implode(',', $klist) . ') VALUES (' . implode(',', $values) . ')';
		return $this->execute($sql);
	}
	
	public function now()
	{
		return $this->quote(strftime('%Y-%m-%d %H:%M:%S'));
	}

	public function rowCount()
	{
		return null;
	}

	public function update($table, $kv, $clause)
	{
		$sql = 'UPDATE ' . $this->quoteTable($table) . ' SET ';
		$keys = array_keys($kv);
		foreach($keys as $k)
		{
			if(substr($k, 0, 1) == '@')
			{
				$v = $kv[$k];
				$sql .= substr($k, 1) . ' = ' . $v . ', ';
			}
			else
			{
				$sql .= $this->quoteObject($k) . ' = ' . $this->quote($kv[$k]) . ', ';
			}
		}
		$sql = substr($sql, 0, -2);
		if(is_string($clause) && strlen($clause))
		{
			$sql .= ' WHERE ' . $clause;
		}
		else if(is_array($clause) && count($clause))
		{
			$sql .= ' WHERE ';
			foreach($clause as $key => $value)
			{
				$sql .= $this->quoteObject($key) . ' = ' . $this->quote($value) . ' AND ';
			}
			$sql = substr($sql, 0, -4);
		}
		return $this->execute($sql);
	}
	
	public function quoteTable($name)
	{
		$name = $this->prefix . $name . $this->suffix;
		$this->quoteObjectRef($name);
		return $name;
	}
	
	public function quoteObject($name)
	{
		$this->quoteObjectRef($name);
		return $name;
	}
	
	public function quote($value)
	{
		$this->quoteRef($value);
		return $value;
	}
		
	public function quoteObjectRef(&$name)
	{
		$name = '"' . $name . '"';
	}

	public function &__get($name)
	{
		$nothing = null;
		if($name == 'schema')
		{
			if(!$this->schema)
			{
				require_once(dirname(__FILE__) . '/dbschema.php');
				$this->schema = DBSchema::schemaForConnection($this);
			}
			return $this->schema;
		}
		if($name == 'dbName')
		{
			return $this->dbName;
		}
		if($name == 'schemaName')
		{
			return $this->schemaName;
		}
		return $nothing;
	}
}

/* while(($row = $rs->next())) { ... } */
class DBDataSet implements DataSet
{
	public $fields = array();
	public $EOF = true;
	public $db;
	public $total = 0;
	protected $resource;
	protected $count = 0;
	
	public function __construct($db, $resource, $query = null, $params = null)
	{
		$this->db = $db;
		$this->resource = $resource;
		$this->EOF = false;
	}
	
	public function next()
	{
		if($this->EOF) return null;
		if(!$this->row())
		{
			$this->EOF = true;
			return null;
		}
		$this->count++;
		return $this->fields;
	}
	
	public function rewind()
	{
	}
	
	public function current()
	{
		return $this->fields;
	}
	
	public function key()
	{
		return $this->count;
	}
	
	public function valid()
	{
		return !$this->EOF;
	}
}

class MySQL extends DBCore
{
	protected $rsClass = 'MySQLSet';
	protected $mysql;
	protected $forceNewConnection = false;
	public $dbms = 'mysql';
	
	public function __construct($params)
	{
		if(isset($params['port']))
		{
			$params['host'] .= ':' . $params['port'];
			unset($params['port']);
		}
		parent::__construct($params);
	}

	protected function autoconnect()
	{
		if(!($this->mysql = mysql_connect($this->params['host'], $this->params['user'], $this->params['pass'], $this->forceNewConnection)))
		{
			return $this->raiseError(null);
		}
		if(strlen($this->params['dbname']))
		{
			if(!mysql_select_db($this->params['dbname'], $this->mysql))
			{
				return $this->raiseError(null);
			}
			$this->dbName = $this->params['dbname'];
		}
		$this->execute("SET NAMES 'utf8'");
		$this->execute("SET sql_mode='ANSI'");
		$this->execute("SET storage_engine='InnoDB'");
		$this->execute("SET time_zone='+00:00'");
	}
	
	public function selectDatabase($name)
	{
		if(!mysql_select_db($name, $this->mysql))
		{
			return $this->raiseError(null);
		}
		$this->dbName = $name;		
	}
	
	protected function execute($sql)
	{
		if(!$this->mysql) $this->autoconnect();
//		echo "[$sql]\n";
		$r = mysql_query($sql, $this->mysql);
		if($r === false)
		{
			return $this->raiseError($sql);
		}
		return $r;
	}
	
	protected function raiseError($query)
	{
		static $neterrors = array(1042, 1043, 1044, 1045, 1129, 1130, 1133, 1152, 1153, 1154, 1155, 1156, 1157, 1158, 1159, 1160, 1162, 1184, 1370, 1203, 1226, 1227, 1251, 1275, 1301, 1317, 1637, 2001, 2002, 2003, 2004, 2005, 2006, 2007, 2009, 2010, 2011, 2012, 2013, 2015, 2020, 2021, 2024, 2025, 2027, 2028, 2036, 2037, 2038, 2039, 2040, 2041, 2042, 2043, 2044, 2045, 2046, 2049, 2055);
		static $syserrors = array(1000, 1001, 1004, 1005, 1006, 1009, 1010, 1011, 1012, 1013, 1014, 1015, 1016, 1017, 1018, 1019, 1021, 1023, 1024, 1025, 1026, 1030, 1033, 1035, 1037, 1039, 1041, 1053, 1078, 1080, 1081, 1082, 1085, 1086, 1098, 1126, 1127, 1135, 1187, 1189, 1190, 1194, 1197, 1199, 1200, 1201, 1202, 1218, 1219, 1236, 1254, 1255, 1256, 1257, 1258, 1259, 1274, 1282, 1285, 1289, 1290, 1296, 1297, 1340, 1341, 1342, 1343, 1344, 1346, 1371, 1374, 1375, 1376, 1377, 1378, 1379, 1380, 1383, 1388, 1389, 1430, 1431, 1432, 1436, 1501, 1524, 1528, 1529, 1533, 1541, 1545, 1547, 1549, 1570, 1573, 1602, 1623, 1627, 1639, 1640);
		static $rollbackerrors = array(1205, 1213);
		
		$class = 'DBException';
		if($this->mysql)
		{
			$errcode = mysql_errno($this->mysql);
			$errstr = mysql_error($this->mysql);
		}
		else
		{
			$errcode = mysql_errno();
			$errstr = mysql_error();			
		}
		if(in_array($errcode, $rollbackerrors))
		{
			$class = 'DBRollbackException';
		}
		else if(in_array($errcode, $neterrors))
		{
			$class = 'DBNetworkException';
			$this->mysql = null;
			$this->forceNewConnection = true;
		}
		else if(in_array($errcode, $syserrors))
		{
			$class = 'DBSystemException';
			$this->mysql = null;
			$this->forceNewConnection = true;
		}
		return $this->reportError($errcode, $errstr, $query, $class);
	}
	
	public function quoteRef(&$string)
	{
		if(is_null($string))
		{
			$string = 'NULL';
		}
		else if(is_bool($string))
		{
			$string = ($string ? "'Y'" : "'N'");
		}
		else if(is_array($string))
		{
			$this->reportError(0, 'Attempt to escape array value', json_encode($string), 'DBException');
		}
		else
		{
			$string = "'" . mysql_real_escape_string($string, $this->mysql) . "'";
		}
	}
		
	public function row($query)
	{
		$row = null;
		$params = func_get_args();
		array_shift($params);
		if(($r =  $this->vquery($query . ' LIMIT 1', $params)))
		{
			$row = mysql_fetch_assoc($r);
		}
		return $row;
	}
	
	public function insertId()
	{
		if(!$this->mysql) return null;
		return mysql_insert_id($this->mysql);
	}

	public function commit()
	{
		try
		{
			$this->execute('COMMIT');
		}
		catch(DBException $e)
		{
			if($e->code == 1213 || $e->code == 1205)
			{
				/* 1213 (ER_LOCK_DEADLOCK) Transaction deadlock. You should rerun the transaction. */
				return false;
			}
			else
			{
				/* An error which doesn't imply that the transaciton should be
				 * automatically retried should be thrown, rather than
				 * returning false. This allows transactions to be
				 * contained within a do { … } while(!$db->commit()) block.
				 */
				throw $e;
			}
		}
		return true;
	}

	public function now()
	{
		return 'NOW()';
	}
	
	public function rowCount()
	{
		return $this->getOne('SELECT FOUND_ROWS()');
	}

	public function quoteTable($name)
	{
		if(!$this->dbName) $this->autoconnect();
		return '"' . $this->dbName . '"."' . $this->prefix . $name . $this->suffix . '"';
	}
}

class MySQLSet extends DBDataSet
{
	public function __construct($db, $resource, $query = null, $params = null)
	{
		if(strlen($query) && false !== strpos($query, '/*!SQL_CALC_FOUND_ROWS*/'))
		{
			$this->total = $db->value('SELECT FOUND_ROWS()');
		}
		parent::__construct($db, $resource, $query);
	}
	
	protected function row()
	{
		return ($this->fields = mysql_fetch_assoc($this->resource));
	}
	
	public function rewind()
	{
		$this->EOF = false;
		$this->fields = null;
		if(false == @mysql_data_seek($this->resource, 0))
		{
			$this->EOF = true;
			return;
		}
	}
}
