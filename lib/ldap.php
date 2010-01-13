<?php

/* Copyright 2009 Mo McRoberts.
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
