<?php

/* Copyright 2009 Mo McRoberts.
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
 * @framework EregansuCore Eregansu Core Library
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 * @year 2010
 * @copyright Mo McRoberts
 * @include uses('db');
 * @sourcebase http://github.com/nexgenta/eregansu/blob/master/
 * @since Available in Eregansu 1.0 and later. 
 */

require_once(dirname(__FILE__) . '/db.php');

/**
 * LDAP database support.
 */

class LDAP extends DBCore
{
	protected $rsClass = 'LDAPSet';
	protected $server;
	protected $dbName;
	public $dbms = 'ldap';
	public $baseDn;

	public function __construct($params)
	{
		if(!isset($params['host'])) $params['host'] = null;
		if(!isset($params['port'])) $params['port'] = 389;
		if(!($this->server = ldap_connect($params['host'], $params['port'])))
		{
			$this->raiseError(null);
		}
		if(!isset($params['user'])) $params['user'] = null;
		if(!isset($params['pass'])) $params['pass'] = null;
		ldap_set_option($this->server, LDAP_OPT_PROTOCOL_VERSION, 3);
		if(!ldap_bind($this->server, $params['user'], $params['pass']))
		{
			$this->raiseError(null);
		}
		if(!isset($params['path'])) $params['path'] = null;
		$dn = $params['path'];
		if(substr($dn, 0, 1) == '/') $dn = substr($dn, 1);
		$dn = explode('/', $dn, 1);
		$this->baseDn = $dn[0];
	}
	
	public function vquery($base, $params)
	{
		if(!is_array($params)) $params = array();
		$attrs = array_shift($params);
		$query = array_shift($params);
//		$query = preg_replace('/\{([^}]+)\}/e', "\$this->quoteTable(\"\\1\")", $query);
		$filter = preg_replace('/\?/e', "\$this->quote(array_shift(\$params))", $query);
		$base = trim($base);
		$base = (strlen($base) ? $base . ',' : '') . $this->baseDn;
		return $this->execute($base, $filter, $attrs);
	}

	
	protected function execute($base, $query, $attrs)
	{
		if(!is_array($attrs))
		{
			if(strlen($attrs))
			{
				$attrs = array($attrs);
			}
			else
			{
				$attrs = array();
			}
		}
		$r = ldap_search($this->server, $base, $query, $attrs);
		if($r === false)
		{
			$this->raiseError($sql);
		}
		return array($this->server, $r);
	}
	
	protected function raiseError($query)
	{
		return $this->reportError(ldap_errno($this->server), ldap_error($this->server), $query);
	}

	public function quoteRef(&$string)
	{
		$string = addslashes($string);
	}
	
	public function insertId()
	{
		return null;
	}
	
	public function commit()
	{
		return true;
	}
	
	public function insert($dn, $object)
	{
		$dn .= ',' . $this->baseDn;
		if(!(ldap_add($this->server, $dn, $object)))
		{
			$this->raiseError('Add ' . $dn);
			return false;
		}
		return true;
	}
}

class LDAPSet extends DBDataSet
{
	protected $server;
	protected $fetched = false;
	protected $entry;
	
	public function __construct($db, $resource)
	{
		$this->db = $db;
		$this->EOF = false;
		$this->server = $resource[0];
		$this->resource = $resource[1];
	}

	protected function row()
	{
		$this->fields = null;
		if($this->fetched)
		{
			$this->entry = ldap_next_entry($this->server, $this->entry);
		}
		else
		{
			$this->entry = ldap_first_entry($this->server, $this->resource);
			$this->fetched = true;
		}
		if(!$this->entry)
		{
			return null;
		}
		$entry = ldap_get_attributes($this->server, $this->entry);
		if(!$entry) return null;
		$this->fields = array();
		$this->fields['dn'] = ldap_get_dn($this->server, $this->entry);
		unset($entry['count']);
		foreach($entry as $k => $v)
		{
			if(is_numeric($k)) continue;
			if(is_array($v))
			{
				if($v['count'] > 1)
				{
					unset($v['count']);
					$this->fields[$k] = $v;
				}
				else
				{
					$this->fields[$k] = $v[0];
				}
			}
			else
			{
				$this->fields[$k] = $v;
			}
		}
		return $this->fields;
	}
}
