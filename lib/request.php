<?php

/* Eregansu: Encapsulation of a request
 *
 * Copyright 2001, 2010 Mo McRoberts.
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
 * @package EregansuLib Eregansu Core Library
 * @year 2009-2011
 * @include uses('request');
 * @since Available in Eregansu 1.0 and later.
 * @task Processing requests
 */

/** 
 * Encapsulation of a request from a client.
 *
 * The Request class and its descendants represents a single request from a user
 * agent of some kind. The \C{Request} class itself is abstract: descendants of \C{Request}
 * are used to represent the various different kinds of request which can be
 * represented, depending upon the current SAPI. For example, an HTTP request
 * from a web browser is represented as an instance of the \C{HTTPRequest} class. 
 *
 * Upon initialisation of the platform, a \C{Request} class instance is instantiated by
 * calling \m{requestForSAPI} with no arguments, and the resulting instance is stored
 * in the \v{$request} global variable.
 *
 * @synopsis $request = Request::requestForSAPI();
 */
abstract class Request implements IObservable
{
	public $sapi; /**< The name of the server API (SAPI) */
	public $data = array(); /**< Application-defined per-request storage */
	public $protocol; /**< The request protocol (e.g., HTTP/1.0) */
	public $method; /**< The request method (e.g., GET, POST, PUT, etc). */
	public $uri; /**< The URI of the request (for example, /foo) */
	public $uriArray; /**< The value of the $uri property, exploded as an array of path components */
	public $self; /**< The full path to the top-level PHP script (i.e., the same value as $_SERVER['PHP_SELF']) */
	public $selfArray; /**< The value of the $self property, exploded as an array of path components */
	public $root; /**< The URI of the application root, including trailing slash */
	public $params; /**< The array of parameters, if any, included in the request (any path components following the current $base and $page) */
	public $objects; /**< The array of objects, if any, included in the request (in the path, following "/-/") */
	public $page; /**< The array of path elements which make up the current page relative to the application root */
	public $base; /**< The URI of the current application base, with trailing slash */
	public $pageUri; /**< The URI of the current page, with trailing slash */
	public $resource; /**< The URI of the current page, without trailing slash; if $pageUri is '/', $resourceUri is '/index' */
	public $types; /**< Accepted MIME types, in order of preference */
	public $contentType; /**< MIME type of the body of the request, if any */
	public $app; /**< The current application instance, if any */
	public $hostname; /**< The server hostname associated with the request, if any */
	public $postData = null;
	public $query = array(); /**< An array of key-value pairs which make up the query parameters */
	public $crumb = array(); /**< An array of breadcrumb elements which may be presented to the user */
	public $backRef = null; /**< A reference to the last-but-one entry in the $crumb array */
	public $lastRef = null; /**< A reference to the last entry in the $crumb array */
	public $stderr;
	public $explicitSuffix = null; /**< The suffix supplied in the URI, if any (e.g., '.html') */
	public $sessionInitialised; /**< A <a href="http://www.php.net/callback">callback</a> which if specified is invoked when the \P{$session} associated with the request is initialised. */
	public $certificate;
	public $certificateInfo;
	public $publicKey;
	public $publicKeyHash;

	protected $session;
	
	/**
	 * Return an instance of a Request class for a specified SAPI.
	 *
	 * Requests are encapsulated as one of several descendants of the \C{Request}
	 * class, depending upon the SAPI in use.
	 *
	 * If no SAPI name is specified when calling \m{requestForSAPI}, the current
	 * SAPI name as identified by PHP (using \f{php_sapi_name}) will be used.
	 * Additionally, if \c{REQUEST_CLASS} is defined and no SAPI name is specified, an instance
	 * of the class named by \c{REQUEST_CLASS} will be created instead of the default for the
	 * current SAPI.
	 *
	 * @type Request
	 * @param[in,optional] string $sapi The name of the SAPI to return an instance for
	 * @return An instance of a request class matching the specified or default SAPI.
	 */
	public static function requestForSAPI($sapi = null)
	{
		if($sapi === null)
		{
			if(defined('REQUEST_CLASS'))
			{
				if(defined('REQUEST_CLASS_PATH'))
				{
					require_once(MODULES_ROOT . REQUEST_CLASS_PATH);
				}
				return new REQUEST_CLASS;
			}
			$sapi = php_sapi_name();
		}
		switch($sapi)
		{
			case 'cli':
				require_once(dirname(__FILE__) . '/cli.php');
				return new CLIRequest();
			default:
				return new HTTPRequest();
		}
	}

	/**
	 * Default constructor for request classes
	 * @internal
	 */
	protected function __construct()
	{
		$this->stderr = fopen('php://stderr', 'w');
		$this->sapi = php_sapi_name();
		$this->siteRoot = (defined('PUBLIC_ROOT') ? PUBLIC_ROOT : dirname($_SERVER['SCRIPT_FILENAME']));
		if(substr($this->siteRoot, -1) != '/') $this->siteRoot .= '/';
		$this->init();
		$this->determineTypes();
	}

	/**
	 * @internal
	 */	
	protected function init()
	{
	}
		
	/**
	 * Initialise the $session property of the request class to be a new instance
	 * of the default session class.
	 * Invokes the $sessionInitialised callback if it is defined.
	 * @internal
	 */
	 
	protected function beginSession()
	{
		$this->session = Session::sessionForRequest($this);
		Observers::invoke('sessionInitialised', $this, $this->session);
		if($this->sessionInitialised)
		{
			call_user_func($this->sessionInitialised, $this, $this->session);
		}
	}
	
	/**
	 * Initialise the $session property of the request class to be a new
	 * TransientSession instance.
	 * @internal
	 */
	public function beginTransientSession()
	{
		$this->session = new TransientSession($this);
	}
	
	/**
	 * @internal
	 */
	protected function determineTypes($acceptHeader = null)
	{
		$ext = null;
		foreach($this->params as $k => $p)
		{
			$x = explode('.', $p);
			if(count($x) > 1)
			{
				require_once(dirname(__FILE__) . '/mime.php');
				$ext = $x[count($x) - 1];
				$t = MIME::typeForExt($ext);
				if(strlen($t))
				{
					$this->params[$k] = $x[0];
				}
				else
				{
					$ext = null;
				}
			}
		}
		foreach($this->objects as $k => $p)
		{
			$x = explode('.', $p);
			if(count($x) > 1)
			{
				require_once(dirname(__FILE__) . '/mime.php');
				$ext = $x[count($x) - 1];
				$t = MIME::typeForExt($ext);
				if(strlen($t))
				{					
					$this->objects[$k] = $x[0];
				}
				else
				{
					$ext = null;
				}
			}
		}
		if($ext !== null)
		{
			$this->explicitSuffix = '.' . $ext;
			$t = MIME::typeForExt($ext);
			$this->types = array(
				$t => array('type' => $t, 'q' => 1),
				);
			return;
		}
		$accept = explode(',', $acceptHeader);
		$this->types = array();
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
			$t = trim($h[0]);
			if(!strcmp($t, '*/*'))
			{
				$t = '*';
			}
			$this->types[$t] = array('type' => $t, 'q' => $q);
		}
	}
	
	/**
	 * @brief Consume the first request parameter as the name of a page.
	 *
	 * Moves the first parameter from \P{Request::$params} to the \P{Request::$page} array and
	 * returns it.
	 *
	 * This has the effect of indicating that the first element of \P{Request::$params} is the
	 * name of a page or matches some other kind of defined route.
	 *
	 * For example, the \C{Router} class will call \m{Request::consume} when the first element of
	 * \P{Request::$params} matches one of its routes and the \l{adjustBase} property of the
	 * route is unset or \c{false}.
	 *
	 * As a result of calling \m{Request::consume}, \P{Request::$pageUri} will be updated
	 * accordingly.
	 *
	 * @type string
	 * @return The first request parameter, or \c{null} if \P{Request::$params} is empty.
	 */
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
		if($this->pageUri == '/')
		{
			$this->resource = '/index';
		}
		else
		{
			$this->resource = substr($this->pageUri, 0, -1);
		}
		return $p;
	}

	/* Return the first item from $this->params, if present, without shifting
	 * it onto $this->page or $this->base
	 */
	public function peek()
	{
		if(isset($this->params[0]))
		{
			return $this->params[0];
		}
		return null;
	}

	/**
	 * Move the first parameter from the request to the base array and return it.
	 *
	 * @type string
	 * @return The first request parameter
	 */
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
	
	public function consumeObject()
	{
		return array_shift($this->objects);
	}
	
	/**
	 * @internal
	 */
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
	
	/**
	 * @internal
	 */
	public function __isset($name)
	{
		if($name == 'session') return true;
		return false;
	}

	/**
	 * @brief Add an element to the breadcrumb array.
	 *
	 * \m{Request::addCrumb} adds a new element to the breadcrumb array (\P{Request::$crumb}), optionally with an associated key.
	 * The \p{$info} parameter may be either the name of the current page or an array containing at
	 * least a \l{name} element. The \l{link} element of the array is used as the URI associated
	 * with this entry in the breadcrumb. If the \l{link} element is absent, or the \p{$info} parameter
	 * was a string, it is set to the value of the \P{Request::$pageUri} property.
	 *
	 * If $key is specified, the breadcrumb information is associated with the given value. Subsequent
	 * calls to \m{addCrumb} specifying the same value for \p{$key} will overwrite the previously-specified
	 * information (preserving the original order).
	 *
	 * If \p{$key} is not specified, a numeric key will be generated automatically.
	 *
	 * @param[in] mixed $info Either the name of the current page as should be presented to a user, or an array containing breadcrumb information.
	 * @param[in,optional] string $key An optional key which the breadcrumb information will be associated with.
	 */
	public function addCrumb($info, $key = null)
	{
		if($this->lastRef)
		{
			$this->backRef = $this->lastRef;
		}
		if(!is_array($info))
		{
			$info = array('name' => $info);
		}
		if(!isset($info['link']))
		{
			$info['link'] = $this->pageUri;
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
	
	public function write($str)
	{
		echo $str;
	}
	
	public function err($str)
	{
		fwrite($this->stderr, $str);
	}
	
	public function flush()
	{
		flush();
	}
	
	public function header($name, $value = null, $replace = true)
	{
		if($value === null)
		{
			if(function_exists('header_remove'))
			{
				header_remove($name);
			}
		}
		else if($name == 'Status')
		{
			header($value);
		}
		else
		{	
			header($name . ': ' . $value, $replace);
		}
	}
	
	public function setCookie($name, $value = null, $expires = 0, $path = null, $domain = null, $secure = false, $httponly = false)
	{
		setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
	}
	
	public function complete()
	{
		exit(0);
	}

	public function abort()
	{
		exit(1);
	}

	/**
	 * Attempt to perform content negotiation.
	 *
	 * Returns an associative array containing a set of HTTP-style headers,
	 * or an HTTP status code (method not allowed, not acceptable, etc.) upon
	 * failure.
	 */
	public function negotiate($methods = null, $contentTypes = null)
	{
		$headers = array();

		if($methods !== null)
		{
			if(!in_array($this->method, $methods))
			{
				return 405; /* Method Not Allowed */
			}
		}
		/* Transform $contentTypes into a form we can use */
		if($contentTypes !== null)
		{
			$match = array();
			$alternates = array();
			$uri = $this->resource;
			foreach($contentTypes as $k => $value)
			{
				if(is_array($value))
				{
					if(!isset($value['q']))
					{
						$value['q'] = 1;
					}
					if(!isset($value['type']))
					{
						$value['type'] = $k;
					}
				}
				else
				{
					$value = array('q' => 1, 'type' => $value);
				}
				if(empty($value['hide']))
				{
					if(isset($value['location']))
					{
						$l = $value['location'];
					}
					else if(isset($value['ext']))
					{
						$l = $uri . '.' . $value['ext'];
						$value['location'] = $l;
					}
					else
					{
						$e = MIME::extForType($value['type']);
						if(strlen($e))
						{
							$l = $uri . $e;
							$value['location'] = $l;
						}
						else
						{
							continue;
						}
					}
					$alternates[] = '{"' . addslashes($l) . '" ' . floatval($value['q']) . ' {type ' . $value['type'] . '}}';
				}
				if(isset($this->types[$value['type']]))
				{
					$value['cq'] = $this->types[$value['type']]['q'];
				}
				else if(isset($this->types['*']))
				{
					$value['cq'] = $this->types['*']['q'];
				}
				else
				{
					continue;
				}
				$key = sprintf('%1.05f-%s', $value['q'] * $value['cq'], $value['type']);
				$match[$key] = $value;		   
			}
			krsort($match);
			$type = array_shift($match);
			if($type === null)
			{
				return 406;
			}
			$headers['Content-Type'] = $type['type'];
			if(isset($type['location']))
			{
				$headers['Content-Location'] = $type['location'];
			}
			$headers['Vary'] = 'Accept';
			if(count($alternates))
			{
				$headers['Alternates'] = implode(', ', $alternates);
			}
		}
		return $headers;
	}
	
	/* @internal
	 * Process a multipart/signed payload 
	 */
	protected function processMultipart($pkcsOptions = PKCS7_NOVERIFY)
	{
		$ct = explode(';', $this->contentType);
		if(!strcmp($ct[0], 'multipart/signed'))
		{
			if($this->postData !== null && !is_array($this->postData))
			{
				$data = "MIME-Version: 1.0\n" .
					"Content-type: " . $this->contentType . "\n" .
					"\n" .
					$this->postData;
				/* Unfortunately, openssl_pkcs7_verify() only operates on disk files */
				$outTmp = tempnam(sys_get_temp_dir(), 'eregansu-tmp-');
				$clearTmp = tempnam(sys_get_temp_dir(), 'eregansu-tmp-');
				$certTmp = tempnam(sys_get_temp_dir(), 'eregansu-tmp-');
				file_put_contents($outTmp, $data);
				/* We _must_ specify a set of 'extra' certificates if we want to retrieve the
				 * cleartext version, even if we have no plans to. For this reason,
				 * Eregansu bundles a copy of the stock certificate bundle,
				 * ca-certificates.crt.
				 */
				$r = openssl_pkcs7_verify($outTmp, $pkcsOptions, $certTmp, array(), dirname(__FILE__) . '/../ca-certificates.crt', $clearTmp);
				if($r === true)
				{
					$this->certificate = file_get_contents($certTmp);
					$cert = openssl_x509_read($this->certificate);
					$buf = file_get_contents($clearTmp);
					$p1 = strpos($buf, "\r\n\r\n");
					$p2 = strpos($buf, "\n\n");
					if(($p1 !== false && $p2 === false) ||
					   ($p1 !== false && $p2 !== false && $p1 < $p2))
					{						
						$data = explode("\r\n\r\n", $buf, 2);
					}
					else
					{
						$data = explode("\n\n", $buf, 2);
					}
					@$this->postData = $data[1];
				}
				else
				{
					$this->certificate = null;
				}
				unlink($outTmp);
				unlink($clearTmp);
				unlink($certTmp);
				$this->certificateUpdated();
				if($r !== true)
				{
					return false;
				}
				$headers = explode("\n", $data[0]);
				$this->contentType = 'application/x-unknown';
				foreach($headers as $h)
				{
					$h = explode(':', $h, 2);
					if(count($h) == 2)
					{
						if(!strcasecmp(trim($h[0]), 'Content-type'))
						{
							$this->contentType = trim($h[1]);
							break;
						}
					}
				}
				return true;
			}			
		}
		return false;
	}

	protected function certificateUpdated()
	{
		if($this->certificate === null)
		{
			$this->certificateInfo = null;
			$this->publicKey = null;
			$this->publicKeyHash = null;
			return;
		}
		$cert = openssl_x509_read($this->certificate);
		$this->certificateInfo = openssl_x509_parse($cert);
		$publicKey = openssl_pkey_get_public($cert);
		$details = openssl_pkey_get_details($publicKey);
		$this->publicKey = $details['key'];
		$this->publicKeyHash = null;
		$matches = array();
		if(preg_match('!^-----BEGIN ([A-Z ]+)-----\s*?([A-Za-z0-9+=/\r\n]+)\s*?-----END \1-----\s*$!D', $this->publicKey, $matches))
		{ 
			$binary = base64_decode(str_replace(array("\r", "\n"), array('', ''), $matches[2]));
			uses('asn1');
			$decoded = ASN1::decodeBER($binary);
			if(isset($decoded[0]['sequence']))
			{
				foreach($decoded[0]['sequence'] as $entry)
				{
					if($entry['type'] == 'BIT-STRING')
					{
						$this->publicKeyHash = openssl_digest(base64_decode($entry['value']), 'SHA1');
						break;
					}
				}
			}
		}
	}
}

/**
 * Encapsulation of an HTTP request.
 */
class HTTPRequest extends Request
{
	public $absoluteBase = null;
	public $absolutePage = null;
	public $secure = false;
	
	protected function init()
	{
		parent::init();
		if(ini_get('expose_php'))
		{
			if(function_exists('header_remove'))
			{
				@header_remove('X-Powered-By');
			}
			else
			{
				@header('X-Powered-By: Eregansu');
			}
		}
	 	/* Use a single sapi value for all of the different HTTP-based PHP SAPIs */
		$this->sapi = 'http';
		$this->protocol = @$_SERVER['SERVER_PROTOCOL'];
		if(!strlen($this->protocol))
		{
			$this->protocol = 'HTTP/1.0';
		}
		$this->method = strtoupper($_SERVER['REQUEST_METHOD']);
		if(strpos($this->method, '_') !== false) $this->method = 'GET';
		$this->uri = $_SERVER['REQUEST_URI'];
		/* On at least some lighttpd+fcgi setups, QUERY_STRING ends up
		 * empty while REQUEST_URI contains ?query...
		 */
		if(($pos = strpos($this->uri, '?')) !== false)
		{
			$qs = substr($this->uri, $pos + 1);
			$this->uri = substr($this->uri, 0, $pos);
			$qs = str_replace(';', '&', $qs);
			parse_str($qs, $this->query);
		}
		else if(isset($_SERVER['QUERY_STRING']))
		{
			$qs = $_SERVER['QUERY_STRING'];
			/* Remove the query from $this->uri if it's there */
			$l = strlen($qs);
			$x = substr($this->uri, 0 - ($l + 1));
			if(!strcmp($x, '?' . $qs))
			{
				$this->uri = substr($this->uri, 0, 0 - ($l + 1));
			}
			$qs = str_replace(';', '&', $qs);
			parse_str($qs, $this->query);
		}
		/* Strip PHP's "magic quotes" from the query parameters, if they're
		 * present.
		 */
		if(get_magic_quotes_gpc())
		{
			foreach($this->query as $k => $v)
			{
				if(is_array($v))
				{
					foreach($v as $k => $x)
					{
						$v[$k] = stripslashes($x);
					}
					$this->query[$k] = $v;
				}
				else
				{
					$this->query[$k] = stripslashes($v);
				}
			}
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
		if($this->pageUri == '/')
		{
			$this->resource = '/index';
		}
		else
		{
			$this->resource = substr($this->pageUri, 0, -1);
		}
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
		$this->processRequestPayload();
		if($this->method == 'POST' && is_array($this->postData) && isset($this->postData['__method']))
		{
			$this->method = $this->postData['__method'];
		}
	}

	protected function processRequestPayload()
	{
		$ct = explode(';', $this->contentType);
		if(!strcmp(trim($ct[0]), 'multipart/signed'))
		{
			$this->postData = file_get_contents('php://input');
			if(!$this->processMultipart())
			{
				return $this->error(Error::BAD_REQUEST, null, null, 'Failed to process multipart/signed block');
			}
		}
		else if(!strcmp(trim($ct[0]), 'application/json'))
		{
			if(($data = file_get_contents('php://input')))
			{
				$this->postData = @json_decode($data, true);
				if($data == null)
				{
					return $this->error(Error::BAD_REQUEST, null, null, 'Failed to unserialise the JSON blob');
				}
			}
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
	
	
	public function redirect($uri, $status = 301, $useHTML = false, $passSid = true)
	{
		if($passSid && $this->session)
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
			$this->session->commit($this);
		}
		if($useHTML)
		{
			echo '<!DOCTYPE html>' . "\n";
			echo '<html><head><meta http-equiv="Refresh" content="1;URL=' . htmlspecialchars($uri) . '" /></head><body><p style="font-family: \'Lucida Grande\', Arial, Helvetica; font-size: 10px;"><a style="color: #ccc;" href="' . htmlspecialchars($uri) . '">Please follow this link if you are not automatically redirected.</a></p></body></html>';
			$this->complete();
		}
		$this->header('Status', 'HTTP/1.0 ' . $status . ' Moved');
		$this->header('Location', $uri);
		$this->flush();
		$this->complete();
	}
}
