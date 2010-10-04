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
	public function vexec($query, $params);
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
	protected static $stderr;
	
	protected $rsClass;
	protected $params;
	protected $schema;
	protected $dbName;
	protected $schemaName;
	public $maxReconnectAttempts = 0;
	public $reconnectDelay = 1;
	public $dbms = 'unknown';
	public $prefix = '';
	public $suffix = '';
	protected $transactionDepth;
	protected $aliases = array();
	
	public static function connect($iristr)
	{
		$iri = self::parseIRI($iristr);
		switch($iri['scheme'])
		{
			case 'mysql':
				require_once(dirname(__FILE__) . '/mysql.php');
				return new MySQL($iri);
			case 'ldap':
				require_once(dirname(__FILE__) . '/ldap.php');
				return new LDAP($iri);
			case 'sqlite3':
				require_once(dirname(__FILE__) . '/sqlite3.php');
				return new SQLite3DB($iri);
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
		if(!isset($iri['host']))
		{
			$iri['host'] = null;
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
		if(isset($this->params['options']['autoreconnect']))
		{
			$this->params['options']['autoreconnect'] = parse_bool($this->params['options']['autoconnect']);
		}
		else
		{
			$this->params['options']['autoreconnect'] = php_sapi_name() == 'cli' ? true : false;
		}
		if(isset($this->params['options']['reconnectquietly']))
		{
			$this->params['options']['reconnectquietly'] = parse_bool($this->params['options']['autoconnect']);
		}
		else
		{
			$this->params['options']['reconnectquietly'] = php_sapi_name() == 'cli' ? false : true;
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
		if(isset($this->params['options']['maxreconnectattempts']))
		{
			$this->maxReconnectAttempts = $this->params['options']['maxreconnectattempts'];
		}
		if(isset($this->params['options']['reconnectdelay']))
		{
			$this->reconnectDelay = $this->params['options']['reconnectdelay'];
		}

	}

	protected function reconnect()
	{
		$dbname = $this->params['dbname'];
		if(!strlen($dbname)) $dbname = '(None)';
		if(!$this->params['options']['reconnectquietly'])
		{
			if(!self::$stderr) self::$stderr = fopen('php://stderr', 'w');
			fwrite(self::$stderr, '[' . strftime('%Y-%m-%d %H:%M:%S %z') . '] Lost connection to database ' . $dbname . ', attempting to reconnect...' . "\n");	
		}
		for($c = 0; !$this->maxReconnectAttempts || ($c < $this->maxReconnectAttempts); $c++)
		{
			try
			{
				if($this->autoconnect())
				{
					if(!$this->params['options']['reconnectquietly'])
					{
						fwrite(self::$stderr, '[' . strftime('%Y-%m-%d %H:%M:%S %z') . '] Connection to database ' . $dbname . ' re-established after ' . $c . ' attempts.' . "\n");
					}
					return true;
				}
			}
			catch(DBNetworkException $e)
			{
			}
			if($this->reconnectDelay)
			{
				sleep($this->reconnectDelay);
			}
			if($c && (($c < 100 && !($c % 10)) || !($c % 100)))
			{
				if(!$this->params['options']['reconnectquietly'])
				{
					fwrite(self::$stderr, '[' . strftime('%Y-%m-%d %H:%M:%S %z') . '] Unable to connect to database ' . $dbname . ' after ' . $c . ' attempts, still trying...' . "\n");
				}
			}
		}
		throw new DBNetworkException(0, 'Failed to reconnect to database ' . $dbname . ' after ' . $this->maxReconnectAttempts);
	}
	
	public function begin()
	{
		$this->execute('START TRANSACTION', false);
		$this->transactionDepth++;
	}
	
	public function rollback()
	{
		$this->execute('ROLLBACK', false);
		if($this->transactionDepth)
		{
			$this->transactionDepth--;
		}
	}

	/* Execute any (parametized) query, expecting a resultset */	
	public /*internal*/ function vquery($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql, true);
	}

	/* Execute any (parametized) query, expecting a boolean result */	
	public function vexec($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql, false) ? true : false;
	}
	
	public function queryArray($query, $params)
	{
		if(($r = $this->vquery($query, $params)))
		{
			return new $this->rsClass($this, $r, $query, $params);
		}
		return null;
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

	/* $rs = $inst->query('SELECT * FROM {sometable} WHERE "field" = ? AND "otherfield" = ?', $something, 27); */
	public function query($query)
	{
		$params = func_get_args();
		array_shift($params);
		if(($r = $this->vquery($query, $params)))
		{
			return new $this->rsClass($this, $r, $query, $params);
		}
		return null;
	}

	public function exec($query)
	{
		$params = func_get_args();
		array_shift($params);
		if($this->vexec($query, $params))
		{
			return true;
		}
		return false;
	}

	public function value($query)
	{
		$params = func_get_args();
		array_shift($params);
		return $this->valueArray($query, $params);
	}

	public function row($query)
	{
		$params = func_get_args();
		array_shift($params);	
		return $this->rowArray($query, $params);
	}

	public function rows($query)
	{
		$params = func_get_args();
		array_shift($params);
		return $this->rowsArray($query, $params);
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
		return $this->execute($sql, false);
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
		return $this->execute($sql, false);
	}
	
	public function quoteTable($name)
	{
		if(isset($this->aliases[$name])) $name = $this->aliases[$name];
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

	public function alias($name, $table = null)
	{
		if(is_array($name))
		{
			foreach($name as $alias => $table)
			{
				$this->aliases[$alias] = $table;
			}
		}
		else if(strlen($table))
		{
			$this->aliases[$name] = $table;
		}
		else
		{
			unset($this->aliases[$name]);
		}
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
	protected $fetched = false;

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
		if(!$this->fetched)
		{
			$this->next();
		}
		return $this->fields;
	}
	
	public function key()
	{
		if(!$this->fetched)
		{
			$this->next();
		}
		return $this->count;
	}
	
	public function valid()
	{
		if(!$this->fetched)
		{
			$this->next();
		}
		return !$this->EOF;
	}
}
