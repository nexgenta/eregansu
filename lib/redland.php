<?php

/* Copyright 2010, 2011 Mo McRoberts.
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

/* These classes provide a wrapper around the Redland RDF extension, which
 * must be installed and loaded in order to use them. See
 * http://librdf.org/bindings/INSTALL.html
 */

abstract class RedlandBase
{
	protected static $defaultWorld;

	protected $resource;
	protected $world;

	public static function world($world = null)
	{
		if($world !== null)
		{
			return $world;
		}
		if(self::$defaultWorld === null)
		{
			self::$defaultWorld = new RedlandWorld();
		}	   
		return self::$defaultWorld;
	}

	protected function __construct($res = null, $world = null)
	{
		if($res !== null && !is_resource($res))
		{
			trigger_error('Constructing instance of ' . get_class($this) . ' -- resource is invalid', E_USER_ERROR);
		}
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
			return $uri->resource;
		}
		if($world && is_string($uri))
		{
			return librdf_new_uri($world->resource, $uri);
		}
		return null;
	}

	public function __call($name, $args)
	{
		trigger_error('Call to undefined method ' . get_class($this) . '::' . $name,  E_USER_ERROR);
	}
}

class RedlandWorld extends RedlandBase
{
	const FEATURE_GENID_BASE = 'http://feature.librdf.org/genid-base';
	const FEATURE_GENID_COUNTER = 'http://feature.librdf.org/genid-counter';
	
	public function __construct()
	{
		parent::__construct(librdf_php_get_world());
		$this->world = $this->resource;
		$this->open();
	}
	
/*	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_world($this->resource);
		}
	} */
	
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
	
	public function __construct($storageName = null, $name = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!strlen($name))
		{
			$name = get_class($this) . self::$storageIndex;
		}
		self::$storageIndex++;
		$res = librdf_new_storage($world->resource, $storageName, $name, $this->parseOptions($options));
		if(!is_resource($res))
		{
			trigger_error('Failed to construct storage ' . $name . ' (of type ' . $storageName . ')', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}
	
/*	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_storage($this->resource);
		}
		} */
}

class RedlandModel extends RedlandBase
{
	protected $storage = null;
	
	public function __construct($storage = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!$storage)
		{
			$storage = new RedlandStorage();
		}
		$this->storage = $storage;
		$res = librdf_new_model($world->resource,
								is_object($storage) ? $storage->resource : $storage,
								$this->parseOptions($options));
		if(!is_resource($res))
		{
			trigger_error('Failed to construct new model instance', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}

	public function addStatement($statement)
	{
		librdf_model_add_statement($this->resource, is_object($statement) ? $statement->resource : $statement);
	}

	public function removeStatement($statement)
	{
		librdf_model_remove_statement($this->resource, is_object($statement) ? $statement->resource : $statement);
	}
	
	public function __destruct()
	{
		if($this->resource)
		{
//			librdf_free_model($this->resource);
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
	
	public function serialiseToString($serialiser, $baseUri = null)
	{
		return librdf_serializer_serialize_model_to_string($serialiser->resource, $baseUri === null ? null : $this->parseURI($baseUri), $this->resource);
	}
}

class RedlandParser extends RedlandBase
{
	protected $world;
	
	public function __construct($name = null, $mime = null, $type = null, $world = null)
	{
		$world = RedlandBase::world($world);
		$res = librdf_new_parser($world->resource, $name, $mime, $this->parseURI($type));
		parent::__construct($res, $world);
	}
	
/*	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_parser($this->resource);
		}
		} */

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

class RedlandNode extends RedlandBase
{
	public static function blank($world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node($world->resource), $world);
	}

	public static function blankId($id, $world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node_from_blank_identifier($world->resource, $id), $world);
	}

	public static function literal($text, $world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node_from_literal($world->resource, $text, null, false), $world);
	}

	public static function node($resource, $world = null)
	{
		if(!is_resource($resource))
		{
			trigger_error('Specified resource is not valid in RedlandNode::node()', E_USER_ERROR);
		}
		if(librdf_node_is_literal($resource))
		{
			return new RDFComplexLiteral(null, $resource, null, $world);
		}
		if(librdf_node_is_resource($resource))
		{
			return new RDFURI(librdf_node_get_uri($resource), $world);
		}
		return new RedlandNode($resource, $world);
	}

	public function __construct($node, $world = null)
	{
		$world = RedlandBase::world($world);
		return parent::__construct($node, $world);
	}

	public function __toString()
	{
		return librdf_node_to_string($this->resource);
	}

	public function isBlank()
	{
		return librdf_node_is_blank($this->resource);
	}

	public function uri()
	{
		return new RDFURI(librdf_node_get_uri($this->resource), $this->world);
	}
}

class RDFURI extends RedlandBase
{
	protected $node;

	public function __construct($uri, $world = null)
	{
		$world = RedlandBase::world($world);
		if(is_resource($uri))
		{
			$res = $uri;
		}
		else
		{
			if($uri instanceof RDFURI || $uri instanceof URL)
			{
				$uri = strval($uri);
			}
			$res = librdf_new_uri($world->resource, $uri);
		}
		if(!is_resource($res))
		{
			trigger_error('Invalid URI <' . $uri . '> passed to RDFURI::__construct()', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}
	
/*	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_uri($this->resource);
		}
		} */

	public function __toString()
	{
		return strval(librdf_uri_to_string($this->resource));
	}

	public function node()
	{
		if($this->node === null)
		{
			$this->node = new RedlandNode(librdf_new_node_from_uri($this->world->resource, $this->resource), $this->world);
		}
		return $this->node;
	}
}

class RedlandSerializer extends RedlandBase
{
	public function __construct($name, $mime = null, $uri = null, $world = null)
	{
		$world = RedlandBase::world($world);	
		parent::__construct(librdf_new_serializer($world->resource, $name, $mime, $this->parseURI($mime)), $world);
	}
	
/*	public function __destruct()
	{
		if($this->resource)
		{
			librdf_free_serializer($this->resource);
		}
		} */
	
	public function serializeModelToString(RedlandModel $model, $baseURI = null)
	{
		foreach(RDF::$namespaces as $uri => $prefix)
		{
			librdf_serializer_set_namespace($this->resource, librdf_new_uri($this->world->resource, $uri), $prefix);
		}
		return librdf_serializer_serialize_model_to_string($this->resource, $this->parseURI($baseURI), $model->resource);
	}

	public function serializeModelToFile(RedlandModel $model, $fileName, $baseURI = null)
	{
		foreach(RDF::$namespaces as $uri => $prefix)
		{
			librdf_serializer_set_namespace($this->resource, librdf_new_uri($this->world->resource, $uri), $prefix);
		}
		return librdf_serializer_serialize_model_to_string($this->resource, $fileName, $this->parseURI($baseURI), $model->resource);
	}
}

class RedlandTurtleSerializer extends RedlandSerializer
{
	public function __construct($name = 'turtle', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandRDFXMLSerializer extends RedlandSerializer
{
	public function __construct($name = 'rdfxml-abbrev', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
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

abstract class RDFInstanceBase extends RedlandBase implements ArrayAccess
{
	protected $subject;
	public /*internal*/ $model;
	public $string;

	public function __construct($uri = null, $type = null, $world = null)
	{
		$this->world = RedlandBase::world($world);	
		if($uri === null)
		{
			$this->subject = RedlandNode::blank($world);
		}
		else
		{
			$uri = new RDFURI($uri);
			$this->subject = $uri->node();
		}
		if($type !== null)
		{
			$this->add(RDF::rdf.'type', new RDFURI($type));
		}
	}

	public function node()
	{
		return $this->subject;
	}

	public function subject()
	{
		return $this->subject->uri();
	}

	public function add($predicate, $object)
	{
		if(!is_object($object))
		{
			$object = RedlandNode::literal($object, $this->world);
		}
		else if(!($object instanceof RedlandNode))
		{
			$object = $object->node();
		}
		if(!strcmp($predicate, RDF::rdf.'about') && $this->subject->isBlank())
		{
			if($this->model === null)
			{
				$this->subject = $object;
			}
			else
			{
				$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
				$stream = librdf_model_find_statements($this->model->resource, $query);
				$this->subject = $object;
				while(!librdf_stream_end($stream))
				{
					$statement = librdf_stream_get_object($stream);
					$this->model->removeStatement($statement);
					librdf_statement_set_subject($statement, $this->subject->resource);
					$this->model->addStatement($statement);
					librdf_stream_next($stream);
				}
			}
			return;
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		if($this->model === null)
		{
			$this->model = new RedlandModel(null, null, $this->world);
		}
		$statement = librdf_new_statement_from_nodes(
			$this->world->resource,
			$this->subject->resource, $predicate->resource, $object->resource);
		$this->model->addStatement($statement);
	}

	public function remove($predicate)
	{
		if($this->model == null)
		{
			return;
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			$this->model->removeStatement($statement);
			librdf_stream_next($stream);
		}
	}

	public function __set($key, $value)
	{
		if(strpos($key, ':') === false)
		{
			$this->{$key} = $value;
			return;
		}
		if(is_array($value))
		{
			$this->remove($key);
			foreach($value as $v)
			{
				$this->add($key, $v);
			}
		}
		else
		{
			$this->add($key, $value);
		}
	}

	public function __get($predicate)
	{
		if(strpos($predicate, ':') === false)
		{
			return null;
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		$nodes = array();
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			$nodes[]= RedlandNode::node(librdf_statement_get_object($statement));
			librdf_stream_next($stream);
		}
		return $nodes;
	}


	/* Return the first value for the given predicate */
	public function first($key)
	{
		$predicate = new RDFURI($this->translateQName($key));
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			return new RDFComplexLiteral(null, librdf_statement_get_object($statement), null, $this->world);
		}
		return null;
	}
	
	/* Return the values of a given predicate */
	public function all($key, $nullOnEmpty = false)
	{
		if(!is_array($key)) $key = array($key);
		$values = array();
		foreach($key as $k)
		{
			$predicate = new RDFURI($this->translateQName($k));
			$predicate = $predicate->node();
			$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
			$stream = librdf_model_find_statements($this->model->resource, $query);
			while(!librdf_stream_end($stream))
			{
				$statement = librdf_stream_get_object($stream);
				$values[] = librdf_statement_get_object($statement);
				librdf_stream_next($stream);
			}
		}
		if(count($values))
		{
			return new RDFSet($values);
		}
		if($nullOnEmpty)
		{
			return null;
		}
		return new RDFSet();
	}

	public function offsetExists($offset)
	{
		$predicate = new RDFURI($this->translateQName($offset));
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			if($statement !== null)
			{
				return true;
			}
			break;
		}
		return false;
	}
	
	public function offsetGet($offset)
	{
		return $this->all($offset);
	}
	
	public function offsetSet($offset, $value)
	{
		if($offset === null)
		{
			/* $inst[] = $value; -- meaningless unless $value is a triple */
			if($value instanceof RDFTriple)
			{
				$this->model->addStatement($value);
			}
			else
			{
				return;
			}
		}
		else
		{
			$offset = $this->translateQName($offset);
		}
		if(is_array($value))
		{
			$this->remove($offset);
			foreach($value as $v)
			{
				$this->add($offset, $v);
			}
		}
		else
		{
			$this->add($offset, $value);
		}
	}

	public function offsetUnset($offset)
	{		
		$offset = $this->translateQName($offset);
		$this->remove($offset);
	}

}

class RDFComplexLiteral extends RedlandNode
{
	public $value;
	public $type = null;
	public $lang = null;

	public static function literal($type = null, $value = null, $lang = null, $world = null)
	{
		if(!strcmp($type, 'http://www.w3.org/2001/XMLSchema#dateTime'))
		{
			return new RDFDatetime($value, $world);
		}
		return new RDFComplexLiteral($type, $value, $lang, $world);
	}

	public function __construct($type = null, $value = null, $lang = null, $world = null)
	{
		if($lang !== null && !is_string($lang))
		{
			trigger_error('RDFComplexLiteral::__construct(): specified is not a string (' . $lang . ')', E_USER_ERROR);
		}
		$world = RedlandBase::world($world);
		if($type !== null)
		{
			$this->type = new RDFURI($type);
		}
		if($lang !== null)
		{
			$this->lang = $lang;
		}
		$this->world = $world;
		$this->setValue($value);
	}

	protected function setValue($value)
	{
		if(is_resource($value))
		{
			$this->resource = $value;
		}
		else if($this->type !== null)
		{
			$this->resource = librdf_new_node_from_typed_literal($this->world->resource, $value, null, $this->type === null ? null : $this->type->resource);
		}
		else
		{
			$this->resource = librdf_new_node_from_literal($this->world->resource, $value, $this->lang, false);
		}
		$this->value = librdf_node_to_string($this->resource);
	}

	public function lang()
	{
		return librdf_node_get_literal_value_language($this->resource);
	}
	
	public function type()
	{
		$u = librdf_node_get_literal_value_datatype_uri($this->resource);
		if($u === null)
		{
			return null;
		}
		return librdf_uri_to_string($u);
	}		
}

class RDFXMLLiteral extends RDFComplexLiteral
{
	public function __construct($value, $world = null)
	{
		$world = RedlandBase::world($world);		
		$res = librdf_new_node_from_literal($world->resource, $value, null, true);
		return parent::__construct(null, $res, null, $world);
	}
}

class RDFDocument extends RedlandModel
{
	public $fileURI;
	public $primaryTopic;

	public function __construct($fileURI = null, $primaryTopic = null, $storage = null, $options = null, $world = null)
	{
		$this->fileURI = $fileURI;
		$this->primaryTopic = $primaryTopic;
		parent::__construct($storage, $options, $world);
		error_log('Created new document');
	}

	public function add(RDFInstance $inst, $pos = null)
	{
		if(($inst = $this->merge($inst, $pos)))
		{
			$this->promote($inst);
		}
		return $inst;		
	}

	public function merge(RDFInstance $inst, $post = null)
	{
		if($inst->model === null)
		{
			print_r($inst);
			die('attempt to merge instance with no model');
			return;
		}
		$statements = librdf_model_as_stream($inst->model->resource);
		librdf_model_add_statements($this->resource, $statements);
		$inst->model = $this;
		librdf_model_sync($this->resource);
	}
	
	public function asXML($leader = null)
	{
		$ser = new RedlandRDFXMLSerializer();
		return $ser->serializeModelToString($this);
	}
	
	public function fromDOM(DOMNode $dom)
	{
		die($dom->ownerDocument->saveXML($dom));
	}
}

class RDFTripleSet extends RDFDocument
{
}

