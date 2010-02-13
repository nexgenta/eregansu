<?php

/* Eregansu: Complex object store
 *
 * Copyright 2010 Mo McRoberts.
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
 * @framework Eregansu
 */

uses('model', 'uuid');

class Storable implements ArrayAccess
{
	protected static $models = array();
	
	public static function objectForData($data, $model, $className = null)
	{
		if(!$className)
		{
			$className = 'Storable';
		}
		if(isset(self::$models[$className]))
		{
			self::$models[$className] = $model;
		}
		return new $className($data);
	}
	
	protected function __construct($data)
	{
		foreach($data as $k => $v)
		{
			$this->$k = $v;
		}
		$this->loaded();
	}
	
	public function store()
	{
		return self::$models[get_class($this)]->store($this);
	}
	
	public function reload()
	{
		static $uuid = null;
		
		if(!$uuid)
		{
			$uuid = $this->uuid;
		}
		$keys = array_keys(get_object_vars($this));
		foreach($keys as $k)
		{
			unset($this->$k);
		}
		if(($data = self::$models[get_class($this)]->dataForUUID($uuid)))
		{
			foreach($data as $k => $v)
			{
				$this->$k = $v;
			}
			$this->loaded(true);
		}
	}
	
	protected function loaded($reloaded = false)
	{
	}
	
	public function offsetExists($name)
	{
		return isset($this->$name);
	}
	
	public function offsetGet($name)
	{
		return $this->$name;
	}
	
	public function offsetSet($name, $value)
	{
		$this->$name = $value;
	}
	
	public function offsetUnset($name)
	{
		unset($this->$name);
	}
}

abstract class StorableSet implements DataSet
{
	protected $model;
	public $EOF = true;
	
	public function __construct($model, $args)
	{
		$this->model = $model;	
	}
	
	public function rewind()
	{
	}
	
	public function valid()
	{
		return !$this->EOF;
	}

}

class StaticStorableSet extends StorableSet
{
	protected $list;
	protected $keys;
	protected $storableClass = 'Storable';
	protected $count;
	protected $current;
	
	public function __construct($model, $args)
	{
		parent::__construct($model, $args);
		$this->list = $args['list'];
		$this->rewind();
	}
	
	public function next()
	{
		if(!$this->EOF)
		{
			if(null != ($k = array_shift($this->keys)))
			{
				$this->current = call_user_func(array($this->storableClass, 'objectForData'), $this->list[$k], $this->model, $this->storableClass);
				$this->count++;
				return $this->current;
			}
			$this->EOF = true;
		}
		return null;
	}
	
	public function key()
	{
		return $this->count;
	}
	
	public function current()
	{
		return $this->current;
	}
	
	public function rewind()
	{
		$this->count = 0;
		if(count($this->list))
		{
			$this->EOF = false;
			$this->keys = array_keys($this->list);
		}
	}
}

class Store extends Model
{
	protected $storableClass = 'Storable';
	
	/* The name of the 'objects' table */
	protected $objects = 'objects';
	
	public static function getInstance($args = null, $className = null, $defaultDbIri = null)
	{
		return parent::getInstance($args, ($className ? $className : 'Store'), $defaultDbIri);
	}
	
	public function objectForUUID($uuid)
	{
		if(!($data = $this->dataForUUID($uuid)))
		{
			return null;
		}
		$class = $this->storableClass;
		return call_user_func(array($this->storableClass, 'objectForData'), $data, $this, $this->storableClass);
	}
	
	public function dataForUUID($uuid)
	{
		if(!($row = $this->db->row('SELECT * FROM {' . $this->objects . '} WHERE "uuid" = ?', $uuid)))
		{
			return null;
		}
		$data = json_decode($row['data'], true);
		/* Ensure these are set from the outset */
		$this->retrievedMeta($data, $row);
		return $data;
	}
	
	public function setData($data, $user = null)
	{
		if(is_object($data)) $data = get_object_vars($data);
		if(isset($data['uuid']) && strlen($data['uuid']) == 36)
		{
			$uuid = $data['uuid'];
		}
		else
		{
			$uuid = UUID::generate();
		}
		unset($data['uuid']);
		unset($data['created']);
		unset($data['modified']);
		unset($data['creator']);
		unset($data['modifier']);
		$json = json_encode($data);
		do
		{
			$this->db->begin();
			$entry = $this->db->row('SELECT "uuid" FROM {' . $this->objects . '} WHERE "uuid" = ?', $uuid);
			if($entry)
			{
				$this->db->exec('UPDATE {' . $this->objects . '} SET "data" = ?, "modified" = ' . $this->db->now () . ' "modifier_scheme" = ?, "modifier_uuid" = ? WHERE "uuid" = ?', $json, $user_scheme, $user_uuid, $uuid);
			}
			else
			{
				$this->db->insert($this->objects, array(
					'uuid' => $uuid,
					'data' => $json,
					'@created' => $this->db->now(),
					'creator_scheme' => $user_scheme,
					'creator_uuid' => $user_uuid,
					'@modified' => $this->db->now(),
					'modifier_scheme' => $user_scheme,
					'modifier_uuid' => $user_uuid,
				));
			}
		}
		while(!$this->db->commit());
		$row = $this->db->row('SELECT "uuid", "created", "creator_scheme", "creator_uuid", "modified", "modifier_scheme", "modifier_uuid" FROM {' . $this->objects . '} WHERE "uuid" = ?', $uuid);
		$this->retrievedMeta($data, $row);
		$this->stored($data);
		return $data['uuid'];
	}
	
	protected function retrievedMeta(&$data, $row)
	{
		$data['uuid'] = $row['uuid'];
		$data['created'] = $row['created'];
		if(strlen($data['creator_uuid']))
		{
			$data['creator'] = array('scheme' => $data['creator_scheme'], 'uuid' => $data['creator_uuid']);
		}
		$data['modified'] = $row['modified'];
		if(strlen($data['modifier_uuid']))
		{
			$data['modifier'] = array('scheme' => $data['modifier_scheme'], 'uuid' => $data['modifier_uuid']);
		}	
	}
	
	protected function stored($data)
	{
	}
}