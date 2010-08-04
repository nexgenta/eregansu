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
			return $this->raiseError(null, false);
		}
		if(strlen($this->params['dbname']))
		{
			if(!mysql_select_db($this->params['dbname'], $this->mysql))
			{
				return $this->raiseError(null, false);
			}
			$this->dbName = $this->params['dbname'];
		}
		$this->execute("SET NAMES 'utf8'", false);
		$this->execute("SET sql_mode='ANSI_QUOTES,IGNORE_SPACE,PIPES_AS_CONCAT'", false);
		$this->execute("SET storage_engine='InnoDB'", false);
		$this->execute("SET time_zone='+00:00'", false);
		return true;
	}
	
	public function selectDatabase($name)
	{
		if(!mysql_select_db($name, $this->mysql))
		{
			return $this->raiseError(null);
		}
		$this->dbName = $name;		
	}
	
	protected function execute($sql, $expectResult = false)
	{
		if(!$this->mysql) $this->autoconnect();
		do
		{
	//		echo "[$sql]\n";
			$r = mysql_query($sql, $this->mysql);
			if($r === false)
			{
				$this->raiseError($sql);
			}
		}
		while($r === false);
		return $r;
	}
	
	protected function raiseError($query, $allowReconnect = true)
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
			$this->transactionDepth = 0;
		}
		else if(in_array($errcode, $neterrors))
		{
			$depth = $this->transactionDepth;
			$class = 'DBNetworkException';
			$this->mysql = null;
			$this->transactionDepth = 0;
			$this->forceNewConnection = true;
			if($allowReconnect && $this->params['options']['autoreconnect'])
			{
				if($this->reconnect())
				{
					if($depth)
					{
						/* Allow perform() to catch this and re-try the transaction */
						$class = 'DBRollbackException';
					}
					else
					{
						return false;
					}
				}
				/* Failed to reconnect, throw the exception */
			}
		}
		else if(in_array($errcode, $syserrors))
		{
			$class = 'DBSystemException';
			$this->mysql = null;
			$this->transactionDepth = 0;
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
			$this->execute('COMMIT', false);
		}
		catch(DBException $e)
		{
			if($e->code == 1213 || $e->code == 1205)
			{
				/* 1213 (ER_LOCK_DEADLOCK) Transaction deadlock. You should rerun the transaction. */
				$this->transactionDepth = 0;
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
		return 'NOW()';
	}
	
	public function rowCount()
	{
		return $this->getOne('SELECT FOUND_ROWS()');
	}

	public function quoteTable($name)
	{
		if(!$this->dbName) $this->autoconnect();
		if(isset($this->aliases[$name])) $name = $this->aliases[$name];
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
