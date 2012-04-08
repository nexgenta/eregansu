<?php

/* Copyright 2009-2012 Mo McRoberts.
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
 * @include uses('db');
 * @source http://github.com/nexgenta/eregansu/blob/master/lib/db.php
 */

require_once(dirname(__FILE__) . '/uri.php');

URI::register('mysql', 'Database', array('file' => dirname(__FILE__) . '/db/mysql.php', 'class' => 'MySQL'));
URI::register('sqlite3', 'Database', array('file' => dirname(__FILE__) . '/db/sqlite3.php', 'class' => 'SQLite3'));
URI::register('ldap', 'Database', array('file' => dirname(__FILE__) . '/directory/ldap.php', 'class' => 'LDAP'));

/***********************************************************************
 *
 * Interfaces
 *
 **********************************************************************/

/* The interface that all types of database class must conform to */
interface IDatabase
{
	public function query($query);
}

/* Transactional databases */
interface ITransactional
{
	/* Begin a transaction */
	public function begin();
	/* Roll-back an in-progress transaction */
	public function rollback();
	/* Commit (complete) a transaction */
	public function commit();
	/* Perform the callback $function within a transaction, up to
	 * $maxRetries attempts.
	 */
	public function perform($function, $data = null, $maxRetries = 10);
}

/* SQL Databases */
interface ISQLDatabase extends IDatabase
{
	public function queryArray($query, $params);

	public function insertInto($table, $values);

	public function value($query);
	public function valueArray($query, $params);

	public function row($query);
	public function rowArray($query, $params);

	public function rows($query);
	public function rowsArray($query, $params);
		
	public function exec($query);
	public function execArray($query, $params);

	public function alias($name, $table = null);
	public function quoteObject($name);
	public function quoteObjectRef(&$name);
	public function quoteRef(&$value);
	public function quote($value);

	public function rowCount();
	public function now();
}

/* Document-oriented content stores */
interface IContentStore extends IDatabase
{
	/* Create */
	public function insert($values);
	public function insertId();
	/* Read */
	public function fetch($what);
	/* Update */
	public function update($what, $values);
	/* Delete */
	public function delete($what);
}

/* Directory services (e.g., LDAP) */

interface IDirectoryService extends IDatabase
{
	public function insertAt($dn, $object);
}

interface IDataSet extends Iterator
{
}

/* Deprecated */
interface IDBCore extends ISQLDatabase, ITransactional
{
}
/* Deprecated */
interface DataSet extends IDataSet
{
}


/***********************************************************************
 *
 * Exceptions
 *
 **********************************************************************/

/**
 * Class encapsulating database-related exceptions.
 *
 * @synopsis throw new DBException($code, $message, $dbQuery);
 */
class DBException extends Exception
{
	/**
	 * Error code relating to the exception condition.
	 *
	 * The \P{$code} property contains an error code relating to the exception
	 * condition, usually as supplied by the database system itself.
	 *
	 * @type string
	 * @note \C{DBException} overrides the visibility of \x{Exception::$code}
	 * to make it \k{public}.
	 */
	public $code;
	
	/**
	 * Human-readable error message relating to the exception condition.
	 *
	 * The \P{$errMsg} property contains a human-readable error message
	 * relating to the exception condition, usually as supplied by the
	 * database system itself.
	 *
	 * Unlike the message produced by converting the instance to a string,
	 * \P{$errMsg} does not contain the query which was being executed when
	 * the exception occurred.
	 */
	public $errMsg;

	/**
	 * Text of the database query being performed when the exception condition
	 * occurred.
	 *
	 * The \P{$query} property contains the text of the database query being
	 * performed when the exception condition ocurred, or \k{null} if no
	 * query was in progress at the time.
	 */
	public $query;
	
	/**
	 * The \C{DBException} constructor is responsible for initialising a new
	 * database exception object.
	 *
	 * The constructor will automatically populate the \C{DBException}
	 * instance's properties and generate a complete exception message which is
	 * passed along with \p{$errCode} to \link{http://www.php.net/manual/en/exception.construct.php|Exception::__construct}.
	 */
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

/***********************************************************************
 *
 * Classes
 *
 **********************************************************************/


/* Abstract base class used to establish connections to databases. e.g.:
 *
 * $db = Database::connect('mysql://localhost/example');
 */
 
abstract class Database implements IDatabase
{
	protected static $stderr;

	public $maxReconnectAttempts = 0;
	public $reconnectDelay = 1;
	public $dbms = 'unknown';
	
	protected $params;

	protected $schema;
	protected $dbName;
	protected $schemaName;

	public static function connect($uri)
	{
		if(!is_object($uri))
		{
			$uri = new URI($uri);
		}
		$inst = URI::handlerForScheme($uri->scheme, 'Database', false, $uri);
		if(!is_object($inst))
		{
			throw new DBException(0, 'Unsupported database connection scheme "' . $uri->scheme . '"', null);
		}
		return $inst;
	}
	
	public function __construct($params)
	{
		$this->params = $params;
		if(!isset($this->params->dbName))
		{			
			$p = $this->params->path;
			while(substr($p, 0, 1) == '/')
			{
				$p = substr($p, 1);
			}
			$x = explode('/', $p);
			$this->params->dbName = $x[0];
		}
		if(isset($this->params->options['autoconnect']))
		{
			$this->params->options['autoconnect'] = parse_bool($this->params->options['autoconnect']);
		}
		else
		{
			$this->params->options['autoconnect'] = true;
		}
		if(isset($this->params->options['autoreconnect']))
		{
			$this->params->options['autoreconnect'] = parse_bool($this->params->options['autoconnect']);
		}
		else
		{
			$this->params->options['autoreconnect'] = php_sapi_name() == 'cli' ? true : false;
		}
		if(isset($this->params->options['reconnectquietly']))
		{
			$this->params->options['reconnectquietly'] = parse_bool($this->params->options['autoconnect']);
		}
		else
		{
			$this->params->options['reconnectquietly'] = php_sapi_name() == 'cli' ? false : true;
		}
		if(isset($this->params->options['prefix']))
		{
			$this->prefix = $this->params->options['prefix'];
		}
		if(isset($this->params->options['suffix']))
		{
			$this->suffix = $this->params->options['suffix'];
		}
		if($this->params->options['autoconnect'])
		{
			$this->autoconnect();
		}
		if(isset($this->params->options['maxreconnectattempts']))
		{
			$this->maxReconnectAttempts = $this->params->options['maxreconnectattempts'];
		}
		if(isset($this->params->options['reconnectdelay']))
		{
			$this->reconnectDelay = $this->params->options['reconnectdelay'];
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

	protected function log()
	{
		if(!self::$stderr) self::$stderr = fopen('php://stderr', 'w');
		$args = func_get_args();
		fwrite(self::$stderr, '[' . strftime('%Y-%m-%d %H:%M:%S %z') . '] ' . implode(' ', $args) . "\n");
	}

	abstract protected function raiseError($query, $allowReconnect = true);
	
	protected function reportError($errcode, $errmsg, $sqlString, $class = 'DBException')
	{
		throw new $class($errcode, $errmsg, $sqlString);
	}

	protected function reconnect()
	{
		$dbname = @$this->params['dbname'];
		if(!strlen($dbname)) $dbname = '(None)';
		if(!$this->params->options['reconnectquietly'])
		{
			$this->log('Lost connection to database', $dbname, ', attempting to reconnect...');	
		}
		for($c = 0; !$this->maxReconnectAttempts || ($c < $this->maxReconnectAttempts); $c++)
		{
			try
			{
				if($this->autoconnect())
				{
					if(!$this->params->options['reconnectquietly'])
					{
						$this->log('Connection to database', $dbname, 're-established after', $c, 'attempts');
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
				if(!$this->params->options['reconnectquietly'])
				{
					$this->log('Unable to connect to database', $dbname, 'after', $c, 'attempts, still trying...');
				}
			}
		}
		throw new DBNetworkException(0, 'Failed to reconnect to database ' . $dbname . ' after ' . $this->maxReconnectAttempts);
	}
}

/* Abstract base class implementing ISQLDatabase */
abstract class SQLDatabase extends Database implements ISQLDatabase, ITransactional
{
	protected $rsClass;
	public $prefix = '';
	public $suffix = '';
	protected $transactionDepth;
	protected $aliases = array();
	
	/**** IDatabase support ****/

	/* For compatibility, the arguments can be either way around. New
	 * code should use insertInto() instead.
	 */
	public function insert($table, $kv = null)
	{
		if($kv === null)
		{
			throw new DBException(0, 'Destination relation not specified in SQLDatabase::insert()', null);
		}
		if(is_array($table) && !is_array($kv))
		{
			return $this->insertInto($kv, $table);
		}
		return $this->insertInto($table, $kv);
	}
	
	public function update($table, $kv, $clause = null)
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

	public function fetch($what)
	{
		$params = func_get_args();
		array_shift($params);	
		return $this->rowArray($query, $params);
	}

	/**** ISQLDatabase support ****/
	
	/* Execute any (parametized) query, expecting a boolean result */
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

	/* Execute any (parametized) query, expecting a boolean result */	
	public function execArray($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql, false) ? true : false;
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

	public function queryArray($query, $params)
	{
		if(($r = $this->vquery($query, $params)))
		{
			return new $this->rsClass($this, $r, $query, $params);
		}
		return null;
	}
	
	public function row($query)
	{
		$params = func_get_args();
		array_shift($params);	
		return $this->rowArray($query, $params);
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
		$params = func_get_args();
		array_shift($params);
		return $this->rowsArray($query, $params);
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
	
	public function value($query)
	{
		$params = func_get_args();
		array_shift($params);
		return $this->valueArray($query, $params);
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
	
	public function insertInto($table, $kv)
	{
		$keys = array_keys($kv);
		$klist = array();
		foreach($keys as $k)
		{
			if(substr($k, 0, 1) == '@')
			{
				$values[] = $kv[$k];
				$klist[] = $this->quoteObject(substr($k, 1));
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

	public function now()
	{
		return $this->quote(strftime('%Y-%m-%d %H:%M:%S'));
	}

	public function rowCount()
	{
		return null;
	}

	/**** ITransactional support ****/
	
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

	/* Execute any (parametized) query, expecting a resultset */	
	public /*internal*/ function vquery($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql, true);
	}

	/* Deprecated */
	public function vexec($query, $params)
	{
		if(!is_array($params)) $params = array();
		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$sql = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		return $this->execute($sql, false) ? true : false;
	}	
}

abstract class ContentStore extends Database implements IContentStore
{
}

abstract class DirectoryService extends Database implements IDirectoryService
{
}

/* Deprecated */
abstract class DBCore extends SQLDatabase
{
}

/* while(($row = $rs->next())) { ... } */
class DBDataSet implements IDataSet
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
		throw new DBException(0, 'Dataset cannot be rewound', null);
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
