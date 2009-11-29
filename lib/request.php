<?php

/* Eregansu: Encapsulation of a request
 *
 * Copyright 2009 Mo McRoberts.
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

class Request
{
	public $sapi;
	public $data = array();
	public $method;
	public $uri;
	public $uriArray;
	public $self;
	public $selfArray;
	public $root;
	public $params;
	public $objects;
	public $page;
	public $base;
	public $pageUri;
	public $types; /* Accepted content types, in order of preference */
	public $contentType; /* Type of the body of the request, if any */
	public $app;
	public $hostname;
	public $postData = null;
	public $query = array();
	public $crumb = array();
	public $backRef = null;
	public $lastRef = null;

	/* Callbacks */
	public $sessionInitialised;
	
	protected $session;
	protected $typeMap = array();
	
	public static function requestForScheme()
	{
		$sapi = php_sapi_name();
		switch($sapi)
		{
			case 'cli':
				require_once(dirname(__FILE__) . '/cli.php');
				return new CLIRequest();
			default:
				return new HTTPRequest();
		}
	}

	protected function __construct()
	{
		$this->sapi = php_sapi_name();
		$this->siteRoot = (defined('INSTANCE_ROOT') ? INSTANCE_ROOT : dirname($_SERVER['SCRIPT_FILENAME']));
		if(substr($this->siteRoot, -1) != '/') $this->siteRoot .= '/';
		$this->typeMap['htm'] = 'text/html';
		$this->typeMap['html'] = 'text/html';
		$this->typeMap['xml'] = 'text/xml';
		$this->typeMap['xhtml'] = 'application/xhtml+xml';
		$this->typeMap['rss'] = 'application/rss+xml';
		$this->typeMap['rdf'] = 'application/rdf+xml';
		$this->typeMap['atom'] = 'application/atom+xml';
		$this->typeMap['json'] = 'application/json';
		$this->typeMap['yaml'] = 'application/x-yaml';
		$this->typeMap['txt'] = 'text/plain';
		$this->typeMap['text'] = 'text/plain';
	}

	public function init()
	{
		$this->determineTypes();
	}
	
	protected function beginSession()
	{
		if(defined('SESSION_CLASS'))
		{	
			$name = SESSION_CLASS;
			$this->session = new $name($this);
		}
		else
		{
			$this->session = new Session($this);
		}
		if($this->sessionInitialised)
		{
			call_user_func($this->sessionInitialised, $this, $this->session);
		}
	}
	
	public function beginTransientSession()
	{
		$this->session = new TransientSession($this);
	}
	
	protected function determineTypes($acceptHeader = null)
	{
		$ext = null;
		foreach($this->params as $k => $p)
		{
			$x = explode('.', $p);
			if(count($x) > 1)
			{
				$this->params[$k] = $x[0];
				$ext = $x[count($x) - 1];
			}
		}
		if($ext !== null)
		{
			if(isset($this->typeMap[$ext]))
			{
				if(is_array($this->typeMap[$ext]))
				{
					$this->types = $this->typeMap[$ext];
				}
				else
				{
					$this->types = array($this->typeMap[$ext]);
				}
			}
			return;
		}
		$accept = explode(',', $acceptHeader);
		$types = array();
		$c = 99;
		foreach($accept as $h)
		{
			$h = explode(';', $h);
			$q = 1;
			for($d = 1; $d < count($h); $d++)
			{
				if(substr($h[$d], 0, 2) == 'q=')
				{
					$q = floatval(substr($h[$d], 2));
					break;
				}
			}
			$types[sprintf('%05.02f-%02d', $q, $c)] = trim($h[0]);
			$c--;
		}
		krsort($types);			
		$this->types = array_values($types);
	}
	
	public function consume()
	{
		if(($p = array_shift($this->params)) !== null)
		{
			$this->page[] = $p;
		}
		if(count($this->page))
		{
			$this->pageUri = $this->base . implode('/', $this->page) . '/';
		}
		else
		{
			$this->pageUri = $this->base;
		}
		return $p;
	}

	public function consumeForApp()
	{
		if(($p = array_shift($this->params)) !== null)
		{
			$this->base .= $p . '/';
		}
		if(count($this->page))
		{
			$this->pageUri = $this->base . implode('/', $this->page) . '/';
		}
		else
		{
			$this->pageUri = $this->base;
		}
	}
	
	public function __get($name)
	{
		if($name == 'session')
		{
			if(!$this->session)
			{
				$this->beginSession();
			}
			return $this->session;
		}
	}
	
	public function __isset($name)
	{
		if($name == 'session') return true;
		return false;
	}

	/* addCrumb($array); addCrumb($name, [$link]); */
	public function addCrumb($info, $link = null, $key = null)
	{
		if($this->lastRef)
		{
			$this->backRef = $this->lastRef;
		}
		if(!is_array($info))
		{
			if($link === null)
			{
				$link = $this->pageUri;
			}
			$info = array('name' => $info, 'link' => $link);
		}
		if($key === null)
		{
			$this->crumb[] = $info;
		}
		else
		{
			$this->crumb[$key] = $info;			
		}
		$this->lastRef = $info;
	}	
}

class HTTPRequest extends Request
{
	public $absoluteBase = null;
	public $absolutePage = null;
	public $secure = false;
	
	public function __construct()
	{
		parent::__construct();
	 	/* Use a single sapi value for all of the different HTTP-based PHP SAPIs */		
		$this->sapi = 'http';
		$this->method = strtoupper($_SERVER['REQUEST_METHOD']);
		if(strpos($this->method, '_') !== false) $this->method = 'GET';
		$this->uri = $_SERVER['REQUEST_URI'];
		if(isset($_SERVER['QUERY_STRING']))
		{
			$qs = $_SERVER['QUERY_STRING'];
			$l = strlen($qs);
			$x = substr($this->uri, 0 - ($l + 1));
			if(!strcmp($x, '?' . $qs))
			{
				$this->uri = substr($this->uri, 0, 0 - ($l + 1));
			}
			$qs = str_replace(';', '&', $qs);
			parse_str($qs, $this->query);
		}
		$this->uriArray = $this->arrayFromURI($this->uri);
		$this->self = $_SERVER['PHP_SELF'];
		$this->selfArray = $this->arrayFromURI($this->self);
		/* Determine our root from self */
		$this->root = dirname($this->self);
		if(substr($this->root, -1) != '/') $this->root .= '/';
		$this->base = $this->root;
		$this->params = array();
		$this->page = array();
		$this->objects = array();
		$this->pageUri = $this->root;
		if(!strncmp($this->root, $this->uri, strlen($this->root)))
		{
			$rel = substr($this->uri, strlen($this->root) - 1);
			$pl = $this->arrayFromURI($rel);
			while(($p = array_shift($pl)) !== null)
			{
				if($p == '-') break;
				$this->params[] = $p;
			}
			while(($p = array_shift($pl)) !== null)
			{
				$this->objects[] = $p;
			}
		}
		if(isset($_SERVER['HTTP_HOST']) && strlen($_SERVER['HTTP_HOST']))
		{
			$this->hostname = $_SERVER['HTTP_HOST'];
		}
		else if(isset($_SERVER['SERVER_NAME']) && strlen($_SERVER['SERVER_NAME']))
		{
			$this->hostname = $_SERVER['SERVER_NAME'];
		}
		$this->httpBase = 'http://' . $this->hostname . $this->base;
		$this->httpsBase = 'https://' . $this->hostname . $this->base;
		$this->absoluteBase = ($this->secure ? $this->httpsBase : $this->httpBase);
		$this->absolutePage = $this->absoluteBase;
		if(is_array($_POST) && count($_POST))
		{
			if(get_magic_quotes_gpc())
			{
				$this->postData = $this->stripslashes_deep($_POST);
			}
			else
			{
				$this->postData = $_POST;
			}
		}
		if(isset($_SERVER['CONTENT_TYPE']))
		{
			$this->contentType = $_SERVER['CONTENT_TYPE'];
		}
	}
	
	public function consume()
	{
		$r = parent::consume();
		$this->httpBase = 'http://' . $this->hostname . $this->base;
		$this->httpsBase = 'https://' . $this->hostname . $this->base;
		$this->absoluteBase = ($this->secure ? $this->httpsBase : $this->httpBase);
		$this->absolutePage = ($this->secure ? 'https://' : 'http://') . $this->hostname . $this->pageUri;
		return $r;
	}

	public function consumeForApp()
	{
		$r = parent::consumeForApp();
		$this->httpBase = 'http://' . $this->hostname . $this->base;
		$this->httpsBase = 'https://' . $this->hostname . $this->base;
		$this->absoluteBase = ($this->secure ? $this->httpsBase : $this->httpBase);
		$this->absolutePage = ($this->secure ? 'https://' : 'http://') . $this->hostname . $this->pageUri;
		return $r;
	}
		
	protected function stripslashes_deep($value)
	{
	    $value = is_array($value) ?
			array_map(array($this, 'stripslashes_deep'), $value) :
			stripslashes($value);
	    return $value;
	}
	
	protected function determineTypes($acceptHeader = null)
	{
		if($acceptHeader === null)
		{
			if(isset($_SERVER['HTTP_ACCEPT']))
			{
				$acceptHeader = $_SERVER['HTTP_ACCEPT'];
			}
			if(!strlen($acceptHeader))
			{
				$acceptHeader = '*/*';
			}
		}
		parent::determineTypes($acceptHeader);
	}


	protected function arrayFromURI($uri)
	{
		$array = array();
		$ua = explode('/', $uri);
		foreach($ua as $s)
		{
			if(!strlen($s)) continue;
			$array[] = $s;
		}
		return $array;
	}
	
	public function pageMatch($page)
	{
		$pages = func_get_args();
		if(!count($this->pageArray)) return false;
		foreach($this->pageArray as $p)
		{
			$pp = array_shift($pages);
			if(strcasecmp($pp, $p))
			{
				return false;
			}
		}
		return true;
	}
	
	
	public function redirect($uri, $status = 301, $useHTML = false)
	{
		if($this->session)
		{
			if(($p = strpos($uri, '?')) !== false)
			{
				$qs = substr($uri, $p);
				if(strpos($qs, '?sid=') === false && strpos($qs, ';sid=') === false)
				{
					$uri .= $this->session->usid;
				}
			}
			else
			{
				$uri .= $this->session->qusid;
			}
		}
		if($this->session)
		{
			$this->session->commit();
		}
		if($useHTML)
		{
			echo '<!DOCTYPE html>' . "\n";
			echo '<html><head><meta http-equiv="Refresh" content="1;URL=' . htmlspecialchars($uri) . '" /></head><body><p style="font-family: \'Lucida Grande\', Arial, Helvetica; font-size: 10px;"><a style="color: #ccc;" href="' . htmlspecialchars($uri) . '">Please follow this link if you are not automatically redirected.</a></p></body></html>';
			exit();
		}
		header('HTTP/1.0 ' . $status . ' Moved');
		header('Location: ' . $uri);
		exit();
	}
}
