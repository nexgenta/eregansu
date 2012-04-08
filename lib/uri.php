<?php

/* Support for URIs, stream wrappers and prefixes
 *
 * Copyright 2009-2012 Mo McRoberts.
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

/* TODO:
 *
 * Deprecate URI::parseForOptions()
 *
 * URI::parse should return a URI instance, or -- if registered -- one of its
 * descendants.
 */

 
/* The global $VFS array is associative, where the key is the name of the URL
 * scheme and the value is the name of the class implementing the scheme.
 * The named classes are registered as PHP stream wrappers. PHP doesn’t provide
 * a means to retrieve the class registered as a wrapper for a given scheme,
 * so we must look it up in $VFS instead. This obviously won’t work if the
 * wrapper class doesn’t have an entry in $VFS.
 */

/* Compatibility -- older code may use $VFS instead of $URI_SCHEMES */
if(!isset($URI_SCHEMES) && isset($VFS))
{
	$URI_SCHEMES =& $VFS;
}

interface IVFS
{
}

class URI implements ArrayAccess
{
	/* Well-known namespace prefixes */
	const xml = 'http://www.w3.org/XML/1998/namespace';
	const xmlns = 'http://www.w3.org/2000/xmlns/';
	const xhtml = 'http://www.w3.org/1999/xhtml';
	const dc = 'http://purl.org/dc/elements/1.1/';
	const dcterms = 'http://purl.org/dc/terms/';
	const xsd = 'http://www.w3.org/2001/XMLSchema#';
	const rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
	const rdfs = 'http://www.w3.org/2000/01/rdf-schema#';
	const owl = 'http://www.w3.org/2002/07/owl#';
	const foaf = 'http://xmlns.com/foaf/0.1/';
	const skos = 'http://www.w3.org/2008/05/skos#';
	const time = 'http://www.w3.org/2006/time#';
	const rdfg = 'http://www.w3.org/2004/03/trix/rdfg-1/';
	const event = 'http://purl.org/NET/c4dm/event.owl#';
	const frbr = 'http://purl.org/vocab/frbr/core#';
	const dcmit = 'http://purl.org/dc/dcmitype/';
	const geo = 'http://www.w3.org/2003/01/geo/wgs84_pos#';
	const mo = 'http://purl.org/ontology/mo/';
	const theatre = 'http://purl.org/theatre#';
	const participation = 'http://purl.org/vocab/participation/schema#';
	const xhv = 'http://www.w3.org/1999/xhtml/vocab#';
	const gn = 'http://www.geonames.org/ontology#';
	const exif = 'http://www.kanzaki.com/ns/exif#';
	const void = 'http://rdfs.org/ns/void#';

	protected static $schemes;
	
	protected static $registered = false;
	protected static $namespaces = array();

	public $scheme = null;
	public $port = null;
	public $host = null;
	public $user = null;
	public $pass = null;
	public $path = null;
	public $query = null;
	public $fragment = null;
	public $options = array();

	/**** Static methods ****/
	public static function parse($url)
	{
		$match = array();
		if(preg_match('!(^[a-z0-9+-]+)://(/[^?#]*)?(\?[^#]*)?(#.*)?$!', $url, $match))
		{
			$info = array('scheme' => $match[1], 'host' => null, 'path' => null, 'query' => null, 'fragment' => null);
			if(isset($match[2])) $info['path'] = $match[2];
			if(isset($match[3])) $info['query'] = $match[3];
			if(isset($match[4])) $info['fragment'] = $match[4];
			return $info;
		}
		return parse_url($url);
	}

	/* As parse(), but ensure that 'scheme', 'host', 'path', 'query', 'fragment',
	 * 'user', 'pass' and 'port' are at the very least null. Also break 'query' out
	 * into an 'options' array.
	 */
	public static function parseForOptions($url)
	{
		static $default = array('scheme' => null, 'host' => null, 'user' => null, 'pass' => null, 'host' => null, 'port' => null, 'path' => null, 'query' => null, 'fragment' => null);
		if(!($url = self::parse($url)))
		{
			return null;
		}
		$url = array_merge($default, $url);
		$url['options'] = array();
		if(strlen($url['query']))
		{
			$q = explode(';', str_replace('&', ';', $url['query']));
			foreach($q as $qv)
			{
				$kv = explode('=', $qv, 2);
				if(!isset($kv[1]))
				{
					$kv[1] = null;
				}
				$url['options'][urldecode($kv[0])] = urldecode($kv[1]);
			}
		}
		return $url;
	}
	
	/* Attempt to return an instance of a handler supporting the
	 * specified interface for a given scheme	 
	 */
	public static function handlerForScheme($scheme, $handlerType = 'VFS', $returnName = false, $args = null)
	{
		/* If the scheme is a string, it's the name of a URI scheme which must be
		 * looked up.
		 */
		if(is_string($scheme))
		{
			self::registerSchemes();
			if(!isset(self::$schemes[$scheme]))
			{
				return null;
			}
			$info = self::$schemes[$scheme];
		}
		else
		{
			/* Otherwise, it's assumed to be an array containing scheme handler
			 * information.
			 */
			$info = $scheme;
		}
		/* If the member is just a string, it's a single class name */
		if(is_string($info))
		{		
			$className = $info;	
		}
		else if(strlen($handlerType) && isset($info[$handlerType]))
		{
			/* Otherwise, it's an array of the form ('VFS' => ..., 'Database' => ...,
			 * 'SearchEngine' => ...)
			 */
			$info = $info[$handlerType];
			/* For each handler-type-specific entry, the value can be an array with
			 * 'file' and 'class' members, or a string just specifying the name of
			 * a class.
			 */
			if(is_array($info) && isset($info['file']))
			{
				require_once($info['file']);
			}
			if(is_array($info))
			{
				$className = $info['class'];
			}
			else
			{
				$className = $info;
			}
		}
		/* If the handler-type is specified, the class must conform to its matching
		 * interface.
		 */
		if(strlen($handlerType))
		{
			if(PHP_VERSION_ID >= 50309)
			{
				if(!is_subclass_of($className, 'I' . $handlerType, false))
				{
					return null;
				}
			}
			else
			{
				if(!is_subclass_of($className, 'I' . $handlerType))
				{
					return null;
				}
			}
			
		}
		if($returnName)
		{
			return $className;
		}
		return new $className($args);
	}
	
	/* Register a URI scheme */
	public static function register($scheme, $kind, $classInfo, $overwrite = false)
	{
		if(!is_array($classInfo))
		{
			$classInfo = array('class' => $classInfo);
		}
		if(isset(self::$schemes[$scheme][$kind]) && !$overwrite)
		{
			return false;
		}
		self::$schemes[$scheme][$kind] = $classInfo;
		if($kind == 'VFS')
		{
			stream_wrapper_class_register($scheme, $classInfo['class'], STREAM_IS_URL);
		}
		return true;
	}
	
	/* Register all of the schemes in $URI_SCHEMES */
	public static /*internal*/ function registerSchemes()
	{
		global $URI_SCHEMES;
		
		if(self::$registered)
		{
			return;
		}
		self::$registered = true;		
		self::$schemes =& $URI_SCHEMES;
		if(isset($URI_SCHEMES) && is_array($URI_SCHEMES))
		{
			foreach($URI_SCHEMES as $scheme => $class)
			{
				stream_wrapper_register($scheme, $class, STREAM_IS_URL);
			}
		}
	}
	
	/* Register all of the known URI prefixes */
	public static /*internal*/ function registerPrefixes()
	{
		if(count(self::$namespaces))
		{
			return;
		}
		self::$namespaces = array();
		self::$namespaces[self::xml] = 'xml';
		self::$namespaces[self::xmlns] = 'xmlms';
		self::$namespaces[self::rdf] = 'rdf';
		self::$namespaces[self::rdfs] = 'rdfs';
		self::$namespaces[self::owl] = 'owl';
		self::$namespaces[self::foaf] = 'foaf';
		self::$namespaces[self::skos] = 'skos';
		self::$namespaces[self::time] = 'time';
		self::$namespaces[self::dc] = 'dc';
		self::$namespaces[self::dcterms] = 'dct';
		self::$namespaces[self::rdfg] = 'rdfg';
		self::$namespaces[self::geo] = 'geo';
		self::$namespaces[self::frbr] = 'frbr';
		self::$namespaces[self::xhtml] = 'xhtml';
		self::$namespaces[self::xhv] = 'xhv';
		self::$namespaces[self::dcmit] = 'dcmit';
		self::$namespaces[self::xsd] = 'xsd';
		self::$namespaces[self::gn] = 'gn';
		self::$namespaces[self::exif] = 'exif';
		self::$namespaces[self::void] = 'void';		
	}
	
	public static function merge($url, $base, $onlyIfNonEmpty = false)
	{
		$url = strval($url);
		$base = strval($base);
		if(!strlen($url) && $onlyIfNonEmpty)
		{
			return $url;
		}
		if(!strlen($base))
		{
			return $url;
		}
		$url = parse_url($url);
		$base = parse_url($base);
		if(!isset($base['path']))
		{
			$base['path'] = '/';
		}
		if(!isset($url['scheme']) || !isset($url['host']))
		{
			$url['scheme'] = $base['scheme'];
			$url['host'] = $base['host'];
			if(isset($base['port'])) $url['port'] = $base['port'];
			if(!isset($url['path']) || !strlen($url['path']))
			{
				$url['path'] = $base['path'];
			}
		}
		else
		{
			if(!isset($url['path']) || !strlen($url['path']))
			{
				$url['path'] = '/';
			}
		}
		return $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : null) . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : null) . (isset($url['fragment']) ? '#' . $url['fragment'] : null);
	}
	
	/* Register a new namespace prefix */
	public static function registerPrefix($prefix, $uri, $overwrite = false)
	{
		self::registerPrefixes();
		$uri = strval($uri);
		if($overwrite || !isset(self::$namespaces[$uri]))
		{
			self::$namespaces[$uri] = $prefix;
		}
	}
	
	/* Obtain the prefix for a namespace */
	public static function prefixForUri($uri, $alwaysCreate = false)
	{
		self::registerPrefixes();
		$uri = strval($uri);
		if(isset(self::$namespaces[$uri]))
		{
			return self::$namespaces[$uri];
		}
		if($alwaysCreate)
		{
			$prefix = 'ns' . count(self::$namespaces);
			self::$namespaces[$uri] = $prefix;
			return $prefix;
		}
		return null;
	}

	/* Obtain the namespace URI for a prefix */
	public static function uriForPrefix($prefix, $asString = false)
	{	
		self::registerPrefixes();
		$r = array_search($prefix, self::$namespaces);
		if($r !== false)
		{
			return ($asString ? $r : new URI($r));
		}		
		return null;
	}
	
	/* Contract a URI */
	public static function contractUri($uri, $alwaysContract = false)
	{
		$qname = strval($uri);
		$len = strlen($qname);
		$p = strcspn(strrev($uri), '#?=/ ');
		if($p == $len)
		{
			/* No character to split on */
			return null;
		}
		$p = $len - $p;
		$ns = trim(substr($qname, 0, $p));
		$lname = substr($qname, $p);
		$prefix = self::prefixForUri($ns, $alwaysContract);
		if($prefix !== null)
		{
			return $prefix . ':' . $lname;
		}
		return null;
	}
	
	/* Expand a URI */
	public static function expandUri($uri, $asString = false)
	{
		$s = explode(':', $uri, 2);
		if(count($s) == 2)
		{
			$base = self::uriForPrefix($s[0]);
			if($base !== null)
			{
				$last = substr($base, -1);
				if($last != ' ' && $last != '#' && $last != '/' && $last != '?')
				{
					$uri = $base . ' ' . $s[1];
				}
				$uri = $base . $s[1];
			}
		}
		return ($asString ? $uri : new URI($uri));
	}
	
	/**
	 * Generate a fully-qualified URI for a namespace URI and local name.
	 *
	 * @type string
	 * @param[in] mixed $namespaceURI A string containing a namespace URI, or
	 *   a DOMNode instance whose fully-qualified node name should be returned.
	 * @param[in,optional] string $local The local part to combine with
	 *   \p{$namespaceURI}.
	 * @return On success, returns a fully-qualified URI. 
	 * @note If \p{$namespaceURI} is a \c{DOMNode}, \p{$local} must be \c{null}. If \p{$namespaceURI} is a string, \p{$local} must not be \c{null}.
	 */
	public static function fqname($namespaceURI, $local = null)
	{
		if($local == null)
		{
			if(is_object($namespaceURI))
			{
				$local = $namespaceURI->localName;
				$namespaceURI = $namespaceURI->namespaceURI;
			}
			else
			{
				return $namespaceURI;
			}
		}
		$t = substr($namespaceURI, -1);
		if(!ctype_alnum($t))
		{
			return $namespaceURI . $local;
		}
		return $namespaceURI . ' ' . $local;
	}


	/**** Magic methods ****/
	
	public function __construct($url, $base = null)
	{
		if(is_string($url) && !strncmp($url, '_:', 2))
		{
			$url = array('scheme' => '_', 'path' => substr($url, 2), 'options' => array());
		}
		else if(!is_array($url))
		{			
			$url = self::parseForOptions($url);		   
		}
		if(isset($url['scheme']) && strlen($url['scheme'])) $this->scheme = $url['scheme'];
		if(isset($url['host']) && strlen($url['host'])) $this->host = $url['host'];
		if(isset($url['port']) && strlen($url['port'])) $this->port = $url['port'];
		if(isset($url['user']) && strlen($url['user'])) $this->user = $url['user'];
		if(isset($url['pass']) && strlen($url['pass'])) $this->pass = $url['pass'];
		if(isset($url['path']) && strlen($url['path'])) $this->path = $url['path'];
		if(isset($url['query']) && strlen($url['query'])) $this->query = $url['query'];
		if(isset($url['fragment']) && strlen($url['fragment'])) $this->fragment = $url['fragment'];
		if(isset($url['options'])) $this->options = $url['options'];
		if($base !== null)
		{
			if(is_string($base) || is_array($base))
			{
				$base = new URL($base);
			}
			if(!isset($this->scheme))
			{
				$this->scheme = $base->scheme;
				if(!isset($this->host))
				{
					$this->host = $base->host;
					$this->port = $base->port;
					$this->user = $base->user;
					$this->pass = $base->pass;
					if(!isset($this->path))
					{
						$this->path = $base->path;
						if(!isset($this->query))
						{
							$this->query = $base->query;
							$this->options = $base->options;
							if(!isset($this->fragment))
							{
								$this->fragment = $base->fragment;
							}
						}
					}
					else if(substr($this->path, 0, 1) != '/')
					{
						/* Normalise path */
						$p = $base->path;
						if(substr($p, 0, 1) == '/') $p = substr($p, 1);
						$p1 = explode('/', $p);
						if(count($p1) == 1 && $p1[0] == '')
						{
							$p1 = array();
						}
						$p2 = explode('/', $this->path);					  
						array_pop($p1);
						foreach($p2 as $k => $v)
						{
							if($v == '..')
							{
								array_pop($p1);
							}
							else if($v != '.')
							{
								$p1[] = $v;
							}
						}
						$this->path = '/' . implode('/', $p1);
					}
				}
			}
		}
	}

	public function __toString()
	{
		if($this->scheme == 'urn' || $this->scheme == 'tag' || $this->scheme == 'javascript' || $this->scheme == 'about' || $this->scheme == 'wysiwyg' || $this->scheme == 'view-source' || $this->scheme == 'mailto' || $this->scheme == '_')
		{
			return $this->scheme . ':' . $this->path;
		}
		return (isset($this->scheme) ? ($this->scheme . '://' . $this->host . (isset($this->port) ? ':' . $this->port : null)) : '') . str_replace("'", '%27', $this->path) . (isset($this->query) ? '?' . $this->query : null) . (isset($this->fragment) ? '#' . $this->fragment : null);
	}
	
	/**** ArrayAccess implementation ****/
	public function offsetGet($key)
	{
		if(!property_exists($this, $key))
		{
			trigger_error('Undefined property: URI::$' . $key, E_USER_NOTICE);
			return null;
		}
		if(!isset($this->{$key}))
		{
			return null;
		}
		return $this->{$key};
	}
	
	public function offsetSet($key, $value)
	{
		$this->{$key} = $value;
	}
	
	public function offsetUnset($key)
	{
		unset($this->{$key});
	}
	
	public function offsetExists($key)
	{
		return property_exists($this, $key);
	}

	/* Return the UUID relating to this URI */
	public function uuid($version = 5)
	{
		require_once(dirname(__FILE__) . '/uuid.php');
		return UUID::generate($version, UUID::URL, $this->__to_String());
	}	
}

class URL extends URI
{
	/* Descendants should implement the below methods for supported operations:
	 *
	 *
public bool dir_closedir ( void )
public bool dir_opendir ( string $path , int $options )
public string dir_readdir ( void )
public bool dir_rewinddir ( void )
public bool mkdir ( string $path , int $mode , int $options )
public bool rename ( string $path_from , string $path_to )
public bool rmdir ( string $path , int $options )
public resource stream_cast ( int $cast_as )
public void stream_close ( void )
public bool stream_eof ( void )
public bool stream_flush ( void )
public bool stream_lock ( mode $operation )
public bool stream_metadata ( int $path , int $option , int $var )
public bool stream_open ( string $path , string $mode , int $options , string &$opened_path )
public string stream_read ( int $count )
public bool stream_seek ( int $offset , int $whence = SEEK_SET )
public bool stream_set_option ( int $option , int $arg1 , int $arg2 )
public array stream_stat ( void )
public int stream_tell ( void )
public bool stream_truncate ( int $new_size )
public int stream_write ( string $data )
public bool unlink ( string $path )
public array url_stat ( string $path , int $flags )
     *
     * For operations which are not supported, the method should be left
     * undefined.
     */
}

abstract class VFS
{
	public static function copy($source, $dest, $context = null)
	{
		global $URI_SCHEMES;
		
		URI::registerSchemes();
		if(($info = @self::parse($source)) && isset($info['scheme']) && strlen($info['scheme']) && isset($URI_SCHEMES[$info['scheme']]))
		{
			$inst = new $URI_SCHEMES[$info['scheme']];
			if(method_exists($inst, 'recvFile'))
			{
				$inst->context = $context;
				return $inst->recvFile($source, $dest);
			}
		}
		if(($info = @self::parse($dest)) && isset($info['scheme']) && strlen($info['scheme']) && isset($URI_SCHEMES[$info['scheme']]))
		{
			$inst = new $URI_SCHEMES[$info['scheme']];
			if(method_exists($inst, 'sendFile'))
			{
				$inst->context = $context;
				return $inst->sendFile($source, $dest);
			}
		}
		/* Fall back to PHP’s own implementation */
		return copy($source, $dest);
	}
	
	public static function readlink($path, $context = null)
	{
		global $URI_SCHEMES;

		URI::registerSchemes();
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($URI_SCHEMES[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'readlink'))
			{
				$inst->context = $context;
				return $inst->readlink($path);
			}
		}
		return readlink($path);
	}

	public static function realpath($path, $context = null)
	{
		global $URI_SCHEMES;
		
		URI::registerSchemes();
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($URI_SCHEMES[$info['scheme']]))
		{
			$inst = new $URI_SCHEMES[$info['scheme']];
			if(method_exists($inst, 'realpath'))
			{
				$inst->context = $context;
				return $inst->realpath($path);
			}
		}
		return realpath($path);
	}
}

abstract class XMLNS extends URI
{
}

URI::registerSchemes();

