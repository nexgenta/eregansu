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

/* These classes provide a wrapper around the Redland RDF extension, which
 * must be installed and loaded in order to use them. See
 * http://librdf.org/bindings/INSTALL.html
 */

abstract class RedlandBase
{
	protected static $defaultWorld;
	protected static $defaultStorage;

	protected $resource;
	protected $world;

	public static function world($world = null)
	{
		if($world !== null)
		{
			return $world;
		}
		if(!self::$defaultWorld)
		{
			self::$defaultWorld = new RedlandWorld();
		}
		return self::$defaultWorld;
	}

	protected function __construct($res, $world = null)
	{
		$this->resource = $res;
		$this->world = $world;
	}
	
	protected function parseOptions($options)
	{
		if(is_array($options))
		{
			$opts = array();
			foreach($options as $k => $v)
			{
				if($v === true)
				{
					$v = 'yes';
				}
				else if($v === false)
				{
					$v = 'false';
				}
				else
				{
					$v = "'" . $v . "'";
				}
				$opts[] = $k . '=' . $v;
			}
			return implode(',', $opts);
		}
		return $options;
	}
	
	protected function parseURI($uri, $world = null)
	{
		if(!$world)
		{
			$world = $this->world;
		}
		if(is_resource($uri))
		{
			return $uri;
		}
		if(is_object($uri))
		{
			return $uri;
		}
		if($world && is_string($uri))
		{
			return librdf_new_uri($world->resource, $uri);
		}
		return null;
	}
}

class RedlandWorld extends RedlandBase
{
	const FEATURE_GENID_BASE = 'http://feature.librdf.org/genid-base';
	const FEATURE_GENID_COUNTER = 'http://feature.librdf.org/genid-counter';
	
	public function __construct()
	{
		parent::__construct(librdf_new_world());
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_world($this->resource);
		}
	}
	
	public function open()
	{
		librdf_world_open($this->resource);
	}
	
	public function getFeature($feature)
	{
		return librdf_world_get_feature($this->resource, $this->parseURI($feature, $this));
	}
}

class RedlandStorage extends RedlandBase
{
	protected static $storageIndex = 1;
	
	public function __construct($storageName, $name = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!strlen($name))
		{
			$name = get_class($this) . self::$storageIndex;
		}
		self::$storageIndex++;
		parent::__construct(librdf_new_storage($world->resource, $storageName, $name, $this->parseOptions($options)), $world);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_storage($this->resource);
		}
	}
}

class RedlandModel extends RedlandBase
{
	protected $storage = null;
	
	public function __construct($storage = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!$storage)
		{
			$storage = new RedlandStorage('hashes', null, array('new' => true, 'hash-type' => 'memory'));
		}
		$this->storage = $storage;
		parent::__construct(librdf_new_model($world->resource, $storage->resource, $this->parseOptions($options)), $world);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_model($this->resource);
		}
	}
	
	public function __get($name)
	{
		if($name == 'size')
		{
			return librdf_model_size($this->resource);
		}
		return null;
	}
}

class RedlandParser extends RedlandBase
{
	protected $world;
	
	public function __construct($name = null, $mime = null, $type = null, $world = null)
	{
		$world = RedlandBase::world($world);	
		parent::__construct(librdf_new_parser($world->resource, $name, $mime, $this->parseURI($type)), $world);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_parser($this->resource);
		}
	}

	public function parseFileIntoModel($filename, $baseURI, $model)
	{
		return $this->parseIntoModel('file://' . realpath($filename), $baseURI, $model);
	}
	
	public function parseIntoModel($uri, $baseURI, $model)
	{
		if(0 == librdf_parser_parse_into_model($this->resource, $this->parseURI($uri), $this->parseURI($baseURI), $model->resource))
		{
			return true;
		}
		return false;		
	}
	
	public function parseStringIntoModel($string, $baseURI, $model)
	{
		if(0 == librdf_parser_parse_string_into_model($this->resource, $string, $this->parseURI($baseURI), $model->resource))
		{
			return true;
		}
		return false;
	}
}

class RedlandURI extends RedlandBase
{
	public function __construct($uri, $world = null)
	{
		$world = RedlandBase::world($world);	
		return parent::__construct(librdf_new_uri($world->resource, $uri), $world);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_uri($this->resource);
		}
	}
}

class RedlandSerializer extends RedlandBase
{
	public function __construct($name, $mime = null, $uri = null, $world = null)
	{
		$world = RedlandBase::world($world);	
		parent::__construct(librdf_new_serializer($world->resource, $name, $mime, $this->parseURI($mime)), $world);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_serializer($this->resource);
		}
	}
	
	public function serializeModelToString($model, $baseURI = null)
	{
		return librdf_serializer_serialize_model_to_string($this->resource, $this->parseURI($baseURI), $model->resource);
	}

	public function serializeModelToFile($model, $fileName, $baseURI = null)
	{
		return librdf_serializer_serialize_model_to_string($this->resource, $fileName, $this->parseURI($baseURI), $model->resource);
	}
}

class RedlandJSONSerializer extends RedlandSerializer
{
	public function __construct($name = 'json', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandJSONTriplesSerializer extends RedlandSerializer
{
	public function __construct($name = 'json-triples', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}
