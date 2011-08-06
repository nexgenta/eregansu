<?php

/* Eregansu: RDF object store
 *
 * Copyright 2011 Mo McRoberts.
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
 * @year 2011
 * @include uses('rdfstore');
 * @since Available in Eregansu 1.0 and later. 
 */

require_once(dirname(__FILE__) . '/../lib/rdf.php');
require_once(dirname(__FILE__) . '/store.php');

/**
 * Object store implementation with facilities for storage of instances of
 * \class{RDFInstance}.
 *
 * RDFStore extends Store to store RDF graphs using the JSON encoding
 * described at http://n2.talis.com/wiki/RDF_JSON_Specification
 */

class RDFStore extends Store
{
	protected $storableClass = 'RDFStoredObject';

	/* Ingest RDF from a URI; returns an instance relating to its primary
	 * topic.
	 */
	public function ingestRDF($uri, $refresh = false, $realUri = null, $firstOnly = false)
	{
		$uuid = null;
		$primary = null;
		if(strlen($realUri))
		{
			$primary = $this->objectForIri($realUri, null, null, $firstOnly);
		}
		if($primary == null)
		{
			$primary = $this->objectForIri($uri, null, null, $firstOnly);
		}
		/* A future revision of this code will allow $refresh to be an integer
		 * (to indicate a maximum age) or a string (to indicate
		 * If-modified-since).
		 */
		if($primary !== null && !$refresh)
		{
			return $primary;
		}
		if($primary === null)
		{
			$uuid = UUID::generate();
		}
		else
		{
			$uuid = $this->uuidOfObject($primary);
		}
		$doc = RDF::documentFromURL($uri);
		/* At some point in the future this will be extended to handle
		 * storing document-level information and inline signatures,
		 * rather than solely the primary topic.
		 */
		$primary = $doc['primaryTopic'];
		if(!is_object($primary))
		{
			trigger_error('Failed locate primary topic in ' . $uri, E_USER_NOTICE);
			return null;
		}
		/* Build the list of all of the URIs this document might be known as */
		$uris = array();
		$furi = strval($doc->fileURI);
		if(strlen($furi) && !in_array($furi, $uris))
		{
			$uris[] = strval($furi);
		}
		if(!in_array($uri, $uris))
		{
			$uris[] = $uri;
		}
		if(strlen($realUri) && !in_array($realUri, $uris))
		{
			$uris[] = $realUri;
		}
		$subj = $this->subjectOfObject($primary);
		if(strlen($subj) && strncmp($subj, '#', 1) && strncmp($subj, '_:', 2) && !in_array($subj, $uris))
		{
			$uris[] = strval($subj);
		}
		$data = $this->objectAsArray($primary);
		$data['kind'] = 'graph';
		$data['iri'] = $uris;
		$data['uuid'] = $uuid;
		if(($data = $this->setData($data, null, false)))
		{
			return $this->objectForUuid($data['uuid'], null, null, $firstOnly);
		}
		return null;
	}

	/* Given an instance or array of instances (but NOT an object in
	 * associative array form), return its subject URI.
	 */
	public function subjectOfObject($object)
	{
		if(is_object($object))
		{
			return $object->subject();
		}
		if(is_array($object) && isset($object[0]) && is_object($object[0]))
		{
			return $object[0]->subject();
		}
		return null;
	}

	/* Given an object or array of objects, return it in an associative
	 * array form.
	 */
	public function objectAsArray($object)
	{
		if(is_object($object))
		{
			$array = $object->asArray();
			return $array['value'];
		}
		if(is_array($object) && isset($object[0]))
		{
			$data = array();
			foreach($object as $k => $obj)
			{
				if(is_object($obj))
				{
					$array = $obj->asArray();
					$data[$k] = $array['value'];
				}
				else
				{
					$data[$k] = $obj;
				}
			}
			return $data;
		}
		if(is_array($object))
		{
			return $object;
		}
		return null;
	}
}

class RDFStoredObject extends RDFInstance
{
	protected static $models = array();

	public static function objectForData($data, $model = null, $className = null)
	{
		if(isset($data[0]))
		{
			$list = array();
			foreach($data as $k => $v)
			{
				$list[$k] = self::objectForData($v, $model, $className);
			}
			return $list;
		}
		if(!strlen($className))
		{
			$className = 'RDFStoredObject';
		}
		if(!isset(self::$models[$className]))
		{
			self::$models[$className] = $model;
		}
		$inst = null;
		if(isset($data[RDF::rdf.'type']))
		{
			foreach($data[RDF::rdf.'type'] as $type)
			{
				if(isset($type['type']) && $type['type'] == 'uri' && isset($type['value']))
				{
					if(($inst = RDF::instanceForClass($type['value'])))
					{
						break;
					}
				}
			}
		}
		if($inst)
		{
			$className = get_class($inst);
		}
		else
		{
			$inst = new $className();
		}
		if(!isset(self::$models[$className]))
		{
			self::$models[$className] = $model;
		}
		self::applyProperties($inst, $data, $model);
		$inst->loaded();
		return $inst;
	}

	protected static function applyProperties($inst, $data, $model)
	{
		if(!is_arrayish($data))
		{
			throw new Exception(gettype($data) . ' passed to RDFStoreObject::objectForData(), array expected');
		}
		if(!is_object($inst))
		{
			throw new Exception($inst . ' is not an object');
		}
		foreach($data as $k => $v)
		{
			if(!strlen($k)) continue;
			if(!strcmp($k, 'refcount')) continue;
			if(is_array($v))
			{
				foreach($v as $pk => $s)
				{
					if(is_array($s) && isset($s['type']))
					{
						switch($s['type'])
						{
						case 'uri':
							if(isset($s['value']) && is_string($s['value']))
							{
								$v[$pk] = new RDFURI($s['value']);
							}
							else
							{
								unset($v[$pk]);
							}
							break;
						case 'literal':
							if(isset($s['lang']) || isset($s['datatype']))
							{
								$l = RDFComplexLiteral::literal(isset($s['datatype']) ? $s['datatype'] : null, $s['value']);
								if(isset($s['lang']))
								{
									$l->{RDF::xml . ' lang'}[] = $s['lang'];
								}
								$v[$pk] = $l;
							}
							else
							{
								$v[$pk] = $s['value'];
							}
							break;
						case 'bnode':
							if(!strncmp($s['value'], '_:', 2))
							{
								$s['value'] = '#' . substr($s['value'], 2);
							}
							$v[$pk] = new RDFURI($s['value']);
							break;
						case 'node':
							$v[$pk] = RDFStoredObject::objectForData($s['value'], $model);
							$v[$pk]->refcount++;
							break;
						default:
							/* XXX this should go away */
							if(isset($s['value']))
							{
								$v[$pk] = $s['value'];
							}
//							echo '<pre>'; print_r($v); echo '</pre>';
							//throw new Exception('unhandled RDF/JSON type ' . $s['type']);
						}
					}
				}
			}
			$inst->{$k} = $v;
		}
	}
	
	protected function loaded($reloaded = false)
	{
	}
}
