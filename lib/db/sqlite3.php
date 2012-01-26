<?php

/* Copyright 2010-2012 Mo McRoberts.
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
 
require_once(dirname(__FILE__) . '/../db.php');

class SQLite3DB extends DBCore
{
	protected $rsClass = 'SQLite3Set';
	protected $conn;
	protected $forceNewConnection = false;
	public $dbms = 'sqlite3';
	
	protected function autoconnect()
	{
		$err = null;
		$this->conn = null;
		if(strlen($this->params['path']) && $this->params['path'] != '/')
		{
			$this->selectDatabase($this->params['path']);
		}
		return true;
	}
	
	public function selectDatabase($name)
	{
		$nconn = new SQLite3($name, SQLITE3_OPEN_CREATE|SQLITE3_OPEN_READWRITE);
		if($nconn === false)
		{
			$this->raiseError(null, $err, 0);
		}
		$this->dbName = $name;
		if($this->conn)
		{
			$this->conn->close();
			$this->conn = null;
		}
		$this->conn = $nconn;
		$this->execute('PRAGMA encoding = "utf8"', false);		
	}
	
	public function begin()
	{
		if($this->transactionDepth)
		{
			$this->execute('SAVEPOINT "savepoint' . $this->transactionDepth . '"', false);
		}
		else
		{
			$this->execute('BEGIN TRANSACTION', false);
		}
		$this->transactionDepth++;
	}
	
	public function rollback()
	{
		if($this->transactionDepth > 1)
		{
			$this->execute('ROLLBACK TRANSACTION TO "savepoint' . ($this->transactionDepth - 1) . '"', false);
		}
		else
		{
			$this->execute('ROLLBACK TRANSACTION', false);
		}
		if($this->transactionDepth)
		{
			$this->transactionDepth--;
		}
	}
	
	protected function execute($sql, $expectResult = false)
	{
		$err = null;
		if(!$this->conn) $this->autoconnect();
		do
		{
//			echo "[$sql]\n";
			if($expectResult)
			{
				@$r = $this->conn->query($sql);
			}
			else
			{
				@$r = $this->conn->exec($sql);
			}
			if($r === false)
			{
				$this->raiseError($sql);
			}
		}
		while($r === false);
		return $r;
	}
	
	protected function raiseError($query, $errstr = null, $errcode = null)
	{		
		$class = 'DBException';
		if($this->conn && $errcode !== null)
		{
			$errcode = $this->conn->lastErrorCode();
		}
		if($this->conn && !strlen($errstr))
		{
			$errstr = $this->conn->lastErrorMsg();
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
			$string = "'" . $this->conn->escapeString($string) . "'";
		}
	}
		
	public function rowArray($query, $params)
	{
		if(($r = $this->vquery($query . ' LIMIT 1', $params)))
		{
			return $r->fetchArray(SQLITE3_ASSOC);
		}
		return null;
	}

	public function rowsArray($query, $params)
	{
		if(($r = $this->vquery($query, $params)))
		{
			$rows = array();
			while(($row = $r->fetchArray(SQLITE3_ASSOC)))
			{
				$rows[] = $row;
			}
			return $rows;
		}
		return null;
	}
	
	public function valueArray($query, $params)
	{
		if(($r = $this->vquery($query . ' LIMIT 1', $params)))
		{
			if(($row = $r->fetchArray(SQLITE3_NUM)) && count($row))
			{
				return $row[0];
			}
		}
		return null;
	}
	
	public function insertId()
	{
		if(!$this->conn) return null;
		return $this->conn->lastInsertRowID();
	}

	public function commit()
	{
		try
		{
			if($this->transactionDepth > 1)
			{
				$this->execute('RELEASE SAVEPOINT "savepoint' . ($this->transactionDepth - 1) . '"', false);
			}
			else
			{
				$this->execute('COMMIT TRANSACTION', false);
			}
		}
		catch(DBException $e)
		{
			if($e->code == SQLITE3_BUSY || $e->code == SQLITE3_INTERRUPT)
			{
				try
				{
					if($this->transactionDepth > 1)
					{
						@$this->conn->exec('ROLLBACK TRANSACTION TO "savepoint' . ($this->transactionDepth - 1) . '"');
					}
					else
					{
						@$this->conn->exec('ROLLBACK TRANSACTION');
					}
				}
				catch(Exception $re)
				{
				}
				$this->transactionDepth--;
				return false;
			}
			else
			{
				/* An error which doesn't imply that the transaciton should be
				 * automatically retried should be thrown, rather than
				 * returning false. This allows transactions to be
				 * contained within a do { … } while(!$db->commit()) block.
				 */
				$this->transactionDepth = 0;
				throw $e;
			}
		}
		if($this->transactionDepth)
		{
			$this->transactionDepth--;
		}
		return true;
	}

	public function now()
	{
		return "datetime('now')";
	}
	
	public function rowCount()
	{
		return null;
	}

	public function quoteTable($name)
	{
		if(!$this->dbName) $this->autoconnect();
		if(isset($this->aliases[$name])) $name = $this->aliases[$name];
		return '"' . $this->prefix . $name . $this->suffix . '"';
	}
}

class SQLite3Set extends DBDataSet
{
	public function __construct($db, $resource, $query = null, $params = null)
	{
		if(strlen($query) && false !== strpos($query, '/*!SQL_CALC_FOUND_ROWS*/'))
		{
//			$this->total = $db->value('SELECT FOUND_ROWS()');
			$this->total = 0;
		}
		parent::__construct($db, $resource, $query, $params);
	}
	
	protected function row()
	{
		$this->fetched = true;
		return ($this->fields = $this->resource->fetchArray(SQLITE3_ASSOC));
	}
	
	public function rewind()
	{
		$this->EOF = false;
		$this->fields = null;
		$this->fetched = false;
		if(false == $this->resource->reset())
		{
			$this->EOF = true;
			return;
		}
	}
}
