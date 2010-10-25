<?php

/* Eregansu: Additional stream wrapper support (copying, symbolic links)
 *
 * Copyright 2009, 2010 Mo McRoberts.
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
 
/* The global $VFS array is associative, where the key is the name of the URL
 * scheme and the value is the name of the class implementing the scheme.
 * The named classes are registered as PHP stream wrappers. PHP doesn’t provide
 * a means to retrieve the class registered as a wrapper for a given scheme,
 * so we must look it up in $VFS instead. This obviously won’t work if the
 * wrapper class doesn’t have an entry in $VFS.
 */
 
class URL
{
	protected static $registered = false;
	
	public $scheme = null;
	public $port = null;
	public $host = null;
	public $user = null;
	public $pass = null;
	public $path = null;
	public $query = null;
	public $fragment = null;
	public $options = array();

	public function __construct($url, $base = null)
	{
		if(!is_array($url))
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
		$this->options = $url['options'];
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
		return $this->scheme . '://' . $this->host . (isset($this->port) ? ':' . $this->port : null) . $this->path . (isset($this->query) ? '?' . $this->query : null) . (isset($this->fragment) ? '#' . $this->fragment : null);
	}

	public static function register()
	{
		global $VFS;
		
		if(self::$registered)
		{
			return;
		}
		if(isset($VFS) && is_array($VFS))
		{
			foreach($VFS as $scheme => $class)
			{
				stream_wrapper_register($scheme, $class, STREAM_IS_URL);
			}
		}
		self::$registered = true;		
	}
	
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
		$default = array('scheme' => null, 'host' => null, 'user' => null, 'pass' => null, 'host' => null, 'port' => null, 'path' => null, 'query' => null, 'fragment' => null);
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

	public static function copy($source, $dest, $context = null)
	{
		global $VFS;
		
		self::register();
		if(($info = @self::parse($source)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'recvFile'))
			{
				$inst->context = $context;
				return $inst->recvFile($source, $dest);
			}
		}
		if(($info = @self::parse($dest)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
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
		global $VFS;

		self::register();		
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
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
		global $VFS;
		
		self::register();		
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'realpath'))
			{
				$inst->context = $context;
				return $inst->realpath($path);
			}
		}
		return realpath($path);
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
}
 
