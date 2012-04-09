<?php

/* Eregansu: Classes which can process requests
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

/**
 * @year 2009-2012
 * @include uses('routable');
 * @since Available in Eregansu 1.0 and later.
 * @task Processing requests
 */

/**
 * The interface implemented by all classes which can process requests.
 */

interface IRequestProcessor
{
	public function process(Request $req);
}

/**
 * Route module loader.
 */
abstract class Loader
{
	/**
	 * Attempt to load the module which handles a route.
	 *
	 * @type boolean
	 * @param[in] array $route An associative array containing route information.
	 * @return \c{true} if the module was loaded successfully, \c{false} otherwise.
	 */
	public static function load($route)
	{
		global $MODULE_ROOT;

		if(!is_array($route))
		{
			return $false;
		}
		if(!empty($route['adjustBase']) || !empty($route['adjustModuleBase']))
		{
			if(isset($route['name']))
			{
				$MODULE_ROOT .= $route['name'] . '/';
			}
			else if(substr($route['key'], 0, 1) != '_')
			{
				$MODULE_ROOT .= $route['key'] . '/';
			}
		}
		$f = null;
		if(isset($route['file']))
		{
			$f = $route['file'];
			if(substr($f, 0, 1) != '/' && isset($route['name']) && empty($route['adjustBase']) && empty($route['adjustModuleBase']))
			{
				$f = $route['name'] . '/' . $f;
			}
			if(substr($f, 0, 1) != '/')
			{
				if(!empty($route['fromRoot']))
				{
					$f = MODULES_ROOT . $f;
				}
				else
				{
					$f = $MODULE_ROOT . $f;
				}
			}
			require_once($f);
		}
		return true;
	}
}

/**
 * Base class for all Eregansu-provided routable instances.
 *
 * The \class{Routable} class is the ultimate ancestor of all classes which
 * process \class{Request} instances and perform actions based upon their
 * properties (typically producing some kind of output). The \class{Routable}
 * class implements the [[IRequestProcessor]] interface.
 */
class Routable implements IRequestProcessor
{
	protected $model;
	protected $modelClass = null;
	protected $modelArgs = null;
	protected $crumbName = null;
	protected $crumbClass = null;
	
	/**
	 * Initialise a \class{Routable} instance.
	 *
	 * Constructs an instance of [[Routable]]. If the protected property [[Routable::$modelClass]] has been set, then the class named by that property’s `[[getInstance|Model::getInstance]]()` method will be invoked and its return value will be set as the protected property [[Routable::$model]]. If [[Routable::$modelArgs]] is set, it will be passed as the first parameter in the call to `[[getInstance|Model::getInstance]]()`.
	 */
	public function __construct()
	{
		if(strlen($this->modelClass))
		{
			$this->model = call_user_func(array($this->modelClass, 'getInstance'), $this->modelArgs);
		}
	}
	
	public function process(Request $req)
	{
		if(isset($req->data['crumbName'])) $this->crumbName = $req->data['crumbName'];
		$this->addCrumb($req);
	}
	
	protected function addCrumb(Request $req = null)
	{
		if($req === null)
		{
			return;
		}
		if($this->crumbName !== null)
		{
			$req->addCrumb(array('name' => $this->crumbName, 'class' => $this->crumbClass));
		}
	}
	
	protected function error($code, Request $req = null, $object = null, $detail = null)
	{
		throw new Error($code, $object, $detail);
	}
}

/**
 * Perform a redirect when a route is requested.
 */
class Redirect extends Routable
{
	protected $target = '';
	protected $useBase = true;
	protected $fromPage = false;

	public function process(Request $req)
	{
		$targ = $this->target;
		$useBase = $this->useBase;
		$fromPage = $this->fromPage;
		if(isset($req->data['target']))
		{
			$targ = $req->data['target'];
		}
		if(isset($req->data['useBase']))
		{
			$useBase = $req->data['useBase'];
		}
		if(isset($req->data['fromPage']))
		{
			$fromPage = $req->data['fromPage'];
		}
		if(substr($targ, 0, 1) == '/')
		{
			$targ = substr($targ, 1);
			if($this->useBase)
			{
				$req->redirect($req->base . $targ);
			}
			$req->redirect($req->root . $targ);
		}
		else if($fromPage || strpos($targ, ':') === false)
		{
			$req->redirect($req->pageUri . $targ);
		}
		else if(strlen($targ))
		{			
			$req->redirect($targ);
		}
	}
}

/**
 * A routable class capable of passing a request to child routes.
 */
class Router extends Routable
{
	protected $sapi = array('http' => array(), 'cli' => array());
	protected $routes;

	/**
	 * @internal
	 */
	public function __construct()
	{
		/* Shorthand, as most stuff is web-based */
		$this->routes =& $this->sapi['http'];
		parent::__construct();
	}
	
	protected function getRouteName(Request $req, &$consume)
	{
		if(isset($req->params[0]))
		{
			$consume = true;
			return $req->params[0];
		}
		return null;
	}

	/**
	 * @internal
	 */	
	public function locateRoute(Request $req)
	{
		global $MODULE_ROOT;
		
		if(!isset($this->sapi[$req->sapi])) return null;
		$routes = $this->sapi[$req->sapi];
		if(isset($req->data['routes']))
		{
			$routes = $req->data['routes'];
		}
		$consume = false;
		$k = $this->getRouteName($req, $consume);
		if(!strlen($k))
		{
			$k = '__NONE__';
		}
		if(!isset($routes[$k]) && isset($routes['__DEFAULT__']))
		{
			$k = '__DEFAULT__';
			$consume = false;
		}
		if(isset($routes[$k]))
		{
			$route = $routes[$k];
			if(is_array($req->data))
			{
				$data = $req->data;
				unset($data['name']);
				unset($data['file']);
				unset($data['adjustBase']);
				unset($data['adjustBaseURI']);
				unset($data['adjustModuleBase']);
				unset($data['routes']);
				unset($data['crumbName']);
				unset($data['class']);
				unset($data['_routes']);
				$data = array_merge($data, $route);
			}
			else
			{
				$data = $route;
			}
			$data['key'] = $k;
			$data['_routes'] = $routes;
			$req->data = $data;
			if($consume)
			{
				if(!empty($data['adjustBase']) || !empty($data['adjustBaseURI']))
				{
					$req->consumeForApp();
				}
				else
				{
					$req->consume();
				}
			}
			return $data;
		}
		return null;
	}
	
	public function process(Request $req)
	{
		parent::process($req);
		$route = $this->locateRoute($req);
		if($route)
		{
			if(isset($route['require']))
			{
				if(!($this->authoriseRoute($req, $route))) return false;
			}
			if(!($target = $this->routeInstance($req, $route))) return false;
			return $target->process($req);
		}
		return $this->unmatched($req);
	}
	
	protected function authoriseRoute(Request $req, $route)
	{
		$perms = $route['require'];
		if(!is_array($perms))
		{
			$perms = ($perms ? array($perms) : array());
		}
		if(in_array('*/*', $req->types) || in_array('text/html', $req->types))
		{
			$match = true;
			foreach($perms as $perm)
			{
				if(!isset($req->session->user) || !isset($req->session->user['perms']) || !in_array($perm, $req->session->user['perms']))
				{
					$match = false;
				}
			}
			if(!$match)
			{
				if($req->session->user)
				{
					$p = new Error(Error::FORBIDDEN);
					return $p->process($req);
				}
				else
				{
					$iri = (defined('LOGIN_IRI') ? LOGIN_IRI : $req->root . 'login');
					return $req->redirect($iri . '?redirect=' . str_replace(';', '%3b', urlencode($req->uri)));
				}
			}
		}
		else
		{
			$success = false;
			if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
			{
				uses('auth');
				$iri = $_SERVER['PHP_AUTH_USER'];
				$scheme = null;
				if(($engine = Auth::authEngineForToken($iri, $scheme)))
				{
					if(($user = $engine->verifyToken($req, $scheme, $iri, $_SERVER['PHP_AUTH_PW'])))
					{
						$req->beginTransientSession();
						$req->session->user = $user;
						$success = true;
					}
				}
			}
			if(!$success)
			{
				$req->header('WWW-Authenticate', 'basic realm="' . $req->hostname . '"');
				$p = new Error(Error::AUTHORIZATION_REQUIRED);
				return $p->process($req);
			}
		}
		return true;
	}

	/**
	 * @internal
	 */	
	public function routeInstance(Request $req, $route)
	{
		global $MODULE_ROOT;

		if(!Loader::load($route))
		{
			return $this->error(Error::NOT_IMPLEMENTED, $req, null, 'The route data for this key is not an array');
		}
		if(!isset($route['class']) || !class_exists($route['class']))
		{
			return $this->error(Error::NOT_IMPLEMENTED, $req, null, 'Class ' . $route['class'] . ' is not implemented in ' . (isset($f) ? $f : " current context"));
		}
		$target = new $route['class']();
		if(!$target instanceof IRequestProcessor)
		{
			return $this->error(Error::ROUTE_NOT_PROCESSOR, $req, null, 'Class ' . $route['class'] . ' is not an instance of IRequestProcessor');
		}
		return $target;		
	}
	
	protected function unmatched(Request $req)
	{
		return $this->error(Error::ROUTE_NOT_MATCHED, $req, null, 'No suitable route could be located for the specified path');
	}
}

/**
 * A routable class which encapsulates an application.
 */
class App extends Router
{
	public $parent;
	public $skin;
	public $theme;

	protected static $initialApp = array();

	public static function initialApp($sapi = null)
	{
		global $MODULE_ROOT;

		if(!strlen($sapi))
		{
			$sapi = php_sapi_name();
		}
		if(isset(self::$initialApp[$sapi]))
		{
			return self::$initialApp[$sapi];
		}
		$prefix = str_replace('-', '_', strtoupper($sapi));
		if(defined($prefix . '_MODULE_CLASS'))
		{
			if(defined($prefix . '_MODULE_NAME'))
			{
				$MODULE_ROOT .= constant($prefix . '_MODULE_NAME') . '/';
			}
			if(defined($prefix . '_MODULE_CLASS_PATH'))
			{
				require_once($MODULE_ROOT . constant($prefix . '_MODULE_CLASS_PATH'));
			}
			$appClass = constant($prefix . '_MODULE_CLASS');
			self::$initialApp[$sapi] = $inst = new $appClass;
			return $inst;
		}
		if(isset(self::$initialApp['default']))
		{
			return self::$initialApp['default'];
		}
		if(defined('MODULE_NAME'))
		{
			$MODULE_ROOT .= MODULE_NAME . '/';
		}
		if(defined('MODULE_CLASS'))
		{
			if(defined('MODULE_CLASS_PATH'))
			{
				require_once($MODULE_ROOT . MODULE_CLASS_PATH);
			}
			$appClass = MODULE_CLASS;
			self::$initialApp['default'] = $inst = new $appClass;
			return $inst;
		}
		self::$initialApp['default'] = $inst = new DefaultApp;
		return $inst;
	}

	public function __construct()
	{
		parent::__construct();
		if(!isset($this->routes['login']))
		{
			$this->routes['login'] = array('file' => PLATFORM_ROOT . 'login/app.php', 'class' => 'LoginPage', 'fromRoot' => true);
		}
		$help = array('file' => PLATFORM_PATH . 'cli.php', 'class' => 'CliHelp', 'fromRoot' => true);
		if(!isset($this->sapi['cli']['__DEFAULT__']))
		{
			$this->sapi['cli']['__DEFAULT__'] = $help;
		}
		if(!isset($this->sapi['cli']['__NONE__']))
		{
			$this->sapi['cli']['__NONE__'] = $help;
		}
		if(!isset($this->sapi['cli']['help']))
		{
			$this->sapi['cli']['help'] = $help;
		}		
	}
	
	public function process(Request $req)
	{
		$this->parent = $req->app;
		$req->app = $this;
		try
		{
			$r = parent::process($req);
			while(is_object($r) && $r instanceof IRequestProcessor)
			{
				$r = $r->process($req);
			}
		}
		catch(Error $e)
		{
			$e->process($req);
			throw $e;
		}
		$req->app = $this->parent;
		$this->parent = null;
	}
}

/**
 * The default application class.
 */
class DefaultApp extends App
{
	public function __construct()
	{
		global $HTTP_ROUTES, $CLI_ROUTES, $MQ_ROUTES;
		
		$this->sapi['http'] = $HTTP_ROUTES;
		$this->sapi['cli'] = $CLI_ROUTES;
		$this->sapi['mq'] = $MQ_ROUTES;
		parent::__construct();
		$help = array('file' => PLATFORM_PATH . 'cli.php', 'class' => 'CliHelp', 'fromRoot' => true);
		if(!isset($this->sapi['cli']['__DEFAULT__']))
		{
			$this->sapi['cli']['__DEFAULT__'] = $help;
		}
		if(!isset($this->sapi['cli']['__NONE__']))
		{
			$this->sapi['cli']['__NONE__'] = $help;
		}
		if(!isset($this->sapi['cli']['help']))
		{
			$this->sapi['cli']['help'] = $help;
		}
		if(!isset($this->sapi['cli']['setup']))
		{
			$this->sapi['cli']['setup'] = array('file' => PLATFORM_PATH . 'cli.php', 'class' => 'CliSetup', 'fromRoot' => true);
		}
		if(!isset($this->sapi['cli']['silk']))
		{
			$this->sapi['cli']['silk'] = array('file' => PLATFORM_ROOT . 'silk/app.php', 'class' => 'Silk', 'fromRoot' => true);
		}
	}
}

/**
 * Route requests to a particular app based upon a domain name.
 */
class HostnameRouter extends DefaultApp
{
	public function __construct()
	{
		global $HOSTNAME_ROUTES;
		
		parent::__construct();
		
		$this->sapi['http'] = $HOSTNAME_ROUTES;
	}
	
	protected function getRouteName(Request $req, &$consume)
	{
		if($req->sapi == 'http')
		{
			return $req->hostname;
		}
		return parent::getRouteName($req, $consume);
	}
}

/**
 * Routable class designed to support presenting views of data objects.
 *
 * The \class{Proxy} class is a descendant of \class{Router} intended to be
 * used in situations where objects are retrieved via a \class{Model} and
 * presented according to the \class{Request}. That is, conceptually,
 * descendants of this class are responsible for proxying objects from storage
 * to presentation. \class{Page} and \class{CommandLine} are notable
 * descendants of \class{Proxy}.
 */
class Proxy extends Router
{
	public static $willPerformMethod;

	public $request;
	public $proxyUri;
	protected $supportedTypes = array();
	protected $supportedMethods = array('GET','HEAD');
	protected $noFallThroughMethods = array('GET', 'HEAD', '__CLI__', '__MQ__');
	protected $object = null;
	protected $sessionObject = null;
	protected $sendNegotiateHeaders = true;

	protected function unmatched(Request $req)
	{
		$this->request = $req;
		$this->proxyUri = $this->request->pageUri;
		$method = $req->method;
		if(!$this->getObject())
		{
			$this->request = null;
			$this->sessionObject = null;
			return false;
		}
		$r = $req->negotiate($this->supportedMethods, $this->supportedTypes);
		if(is_array($r))
		{
			$type = $r['Content-Type'];
			if($this->sendNegotiateHeaders)
			{
				foreach($r as $k => $value)
				{
					$req->header($k, $value);
				}
			}
		}
		else
		{
			$desc = array(
				'Failed to negotiate ' . $method . ' with ' . get_class($this),
				'Requested content types:',
				);
			ob_start();
			print_r($req->types);
			$desc[] = ob_get_clean();
			$desc[] = 'Supported content types:';
			ob_start();
			print_r($this->supportedTypes);
			$desc[] = ob_get_clean();
			$req->header('Allow', implode(', ', $this->supportedMethods));
			return $this->error($r, $req, null, implode("\n\n", $desc));
		}
		if(self::$willPerformMethod)
		{
			call_user_func(self::$willPerformMethod, $this, $method, $type);
		}
		$r = $this->performMethod($method, $type);
		$this->object = null;
		$this->request = null;
		$this->sessionObject = null;
		return $r;
	}
	
	protected function performMethod($method, $type)
	{
		$methodName = 'perform_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $method);
		if(!method_exists($this, $methodName))
		{
			return $this->error(Error::METHOD_NOT_IMPLEMENTED, $this->request, null, 'Method ' . $methodName . ' is not implemented by ' . get_class($this));
		}
		$r = $this->$methodName($type);
		if($r && !in_array($method, $this->noFallThroughMethods))
		{
			$r = $this->perform_GET($type);
		}
		return $r;
	}

	protected function addCrumb(Request $req = null)
	{
		if(!$req)
		{
			$req = $this->request;
		}
		parent::addCrumb($req);
	}
	
	protected function error($code, Request $req = null, $object = null, $detail = null)
	{
		if(!$req)
		{
			$req = $this->request;
		}
		parent::error($code, $req, $object, $detail);
		$this->request = null;
		$this->sessionObject = null;
	}
	
	protected function getObject()
	{
		return true;
	}

	protected function putObject($data)
	{
		return true;
	}

	protected function perform_OPTIONS($type)
	{
		$this->request->header('Allow', implode(' ', $this->supportedMethods));
		$this->request->header('Content-length', 0);
		$this->request->header('Content-type');
		return false;
	}
	
	protected function perform_HEAD($type)
	{
		return $this->perform_GET($type);
	}
	
	protected function perform_GET($type)
	{
		switch($type)
		{
		case 'text/xml':
		case 'application/xml':
			return $this->perform_GET_XML($type);
		case 'application/json':
			return $this->perform_GET_JSON($type);
		case 'application/x-rdf+json':
		case 'application/rdf+json':
			return $this->perform_GET_RDFJSON($type);
		case 'application/rdf+xml':
			return $this->perform_GET_RDF($type);
		case 'text/turtle':
			return $this->perform_GET_Turtle($type);
		case 'application/x-yaml':
			return $this->perform_GET_YAML($type);
		case 'application/atom+xml':
			return $this->perform_GET_Atom($type);
		case 'text/plain':
			return $this->perform_GET_Text($type);
		case 'text/html':
			return $this->perform_GET_HTML($type);
		case 'text/javascript':
			return $this->perform_GET_JS($type);
		}
		/* Try to construct a method name based on the MIME type */
		$ext = preg_replace('![^A-Z]!', '_', strtoupper(MIME::extForType($type)));
		if(strlen($ext))
		{
			$methodName = 'perform_GET' . $ext;
			if(method_exists($this, $methodName))
			{
				return $this->$methodName();
			}
		}
		return $this->serialise($type);
	}

	protected function perform_GET_JS($type = 'text/javascript')
	{
		return $this->perform_GET_JSON($type);
	}

	protected function serialise($type, $returnBuffer = false, $sendHeaders = true, $reportError = true)
	{
		if(!($this->object instanceof ISerialisable))
		{
			if($reportError)
			{
				$this->error(Error::TYPE_NOT_SUPPORTED, $this->request, null, 'Object is not serialisable (requested ' . $type . ')');
			}
			return false;
		}
		if(false === ($r = $this->object->serialise($type, $returnBuffer, $this->request, $sendHeaders)))
		{
			if($reportError)
			{
				$this->error(Error::METHOD_NOT_IMPLEMENTED, $this->request, null, 'Serialisable object does not support ' . $type);
			}
		}
		return $r;
	}
	
	protected function perform_GET_XML($type = 'text/xml')
	{
		return $this->serialise($type);
	}
	
	protected function perform_GET_Text($type = 'text/plain')
	{
		return $this->serialise($type);
	}

	protected function perform_GET_HTML($type = 'text/html')
	{
		return $this->serialise($type);
	}

	protected function perform_GET_JSON($type = 'application/json')
	{
		if(isset($this->request->query['jsonp']))
		{
			$prefix = $this->request->query['jsonp'] . '(';
			$suffix = ')';
			$type = 'text/javascript';
		}
		else if(isset($this->request->query['callback']))
		{
			$prefix = $this->request->query['callback'] . '(';
			$suffix = ')';
			$type = 'text/javascript';
		}
		else
		{
			$prefix = null;
			$suffix = null;
		}
		$this->request->header('Content-type', $type);
		echo $prefix;
		if(isset($this->object))
		{
			if(!($this->object instanceof ISerialisable) || $this->object->serialise($type) === false)
			{
				echo json_encode($this->object);
			}
		}
		else if(isset($this->objects))
		{
			echo json_encode($this->objects);
		}
		echo $suffix;
	}

	protected function perform_GET_RDFJSON()
	{
		if(isset($this->request->query['jsonp']))
		{
			$type = 'text/javascript';
			$prefix = $this->request->query['jsonp'] . '(';
			$suffix = ')';
		}
		else if(isset($this->request->query['callback']))
		{
			$type = 'text/javascript';
			$prefix = $this->request->query['callback'] . '(';
			$suffix = ')';
		}
		else
		{
			$type = 'application/rdf+json';
			$prefix = null;
			$suffix = null;
		}
		$this->request->header('Content-type', $type);
		echo $prefix;
		if(isset($this->object))
		{
			if(!($this->object instanceof ISerialisable) || $this->object->serialise($type) === false)
			{					
				echo json_encode($this->object);
			}
		}
		else if(isset($this->objects))
		{
			echo json_encode($this->objects);
		}
		echo $suffix;
	}

	protected function perform_GET_RDF($type = 'application/rdf+xml')
	{
		return $this->serialise($type);
	}

	protected function perform_GET_Turtle($type = 'text/turtle')
	{
		return $this->serialise($type);
	}
	
	protected function perform_GET_YAML($type = 'application/x-yaml')
	{
		return $this->serialise($type);
	}
	
	protected function perform_GET_Atom($type = 'application/atom+xml')
	{
		return $this->serialise($type);
	}

	protected function perform_POST($type)
	{
		return $this->error(Error::UNSUPPORTED_MEDIA_TYPE, null, null, get_class($this) . '::perform_POST() cannot handle this request');
	}

	protected function perform_PUT($type)
	{
		switch($this->request->contentType)
		{
			case 'application/x-www-form-urlencoded':
			case 'multipart/form-data':
				return $this->putObject($this->request->postData);
			case 'application/json':
				return $this->putObject($this->request->postData);
		}
		return $this->error(Error::UNSUPPORTED_MEDIA_TYPE, null, null, $this->request->contentType . ' is not supported by ' . get_class($this) . '::perform_PUT()');
	}
	
	protected function perform_DELETE($type)
	{
	}
	
	public function __get($name)
	{
		if($name == 'session')
		{
			if(!$this->sessionObject && $this->request)
			{
				$this->sessionObject = $this->request->session;
			}
			return $this->sessionObject;
		}
	}
}

/**
 * Interface implemented by command-line routable classes.
 */
interface ICommandLine
{
	function main($args);
}

/**
 * Encapsulation of a command-line interface handler.
 */
abstract class CommandLine extends Proxy implements ICommandLine
{
	const no_argument = 0;
	const required_argument = 1;
	const optional_argument = 2;

	protected $supportedMethods = array('__CLI__');
	protected $supportedTypes = array('text/plain');
	protected $args;
	protected $options = array();
	protected $minArgs = 0;
	protected $maxArgs = null;
	
	protected $optopt = -1;
	protected $optarg = null;
	protected $optind = 0;
	protected $opterr = true;
	protected $longopt = null;
	
	protected function getObject()
	{
		$this->args = $this->request->params;
		if(!($this->checkargs($this->args)))
		{
			return false;
		}
		return true;
	}
	
	protected function perform___CLI__()
	{
		if(0 === ($r = $this->main($this->args)))
		{
			exit(0);
		}
		if($r === true)
		{
			exit(0);
		}
		if(!is_numeric($r) || $r < 0)
		{
			$r = 1;
		}
		exit($r);
	}
	
	protected function checkargs(&$args)
	{
		while(($c = $this->getopt($args)) != -1)
		{
			if($c == '?')
			{
				$this->usage();
				return false;
			}
		}
		if($this->minArgs !== null && count($args) < $this->minArgs)
		{
			$this->usage();
			return false;
		}
		if($this->maxArgs !== null && count($args) > $this->maxArgs)
		{
			$this->usage();
			return false;
		}
		return true;
	}
	
	protected function usage()
	{
		if(isset($this->usage))
		{
			echo "Usage:\n\t" . $this->usage . "\n";
		}
		else
		{
			echo "Usage:\n\t" . str_replace('/', ' ', $this->request->pageUri) . (count($this->options) ? ' [OPTIONS]' : '') . ($this->minArgs ? ' ...' : '') . "\n";
		}
		$f = false;
		foreach($this->options as $long => $opt)
		{
			if(isset($opt['description']) && (!is_numeric($long) || isset($opt['value'])))
			{
				if(is_numeric($long))
				{
					$s = '-' . $opt['value'];					
				}
				else
				{
					$s = '--' . $long . (isset($opt['value']) ? ', -' . $opt['value'] : '');
				}
				if(!$f)
				{
					echo "\nOPTIONS is one or more of:\n";
					$f = true;
				}
				echo sprintf("\t%-25s  %s\n", $s, $opt['description']);
			}
		}
		exit(1);
	}
	
	protected function getopt(&$args)
	{
		$null = null;
		$this->optopt = -1;
		$this->optarg = null;
		$this->longopt =& $null;
		
		while($this->optind < count($args))
		{
			$arg = $args[$this->optind];
			if(!strcmp($arg, '--'))
			{
				return -1;
			}
			if(substr($arg, 0, 1) != '-')
			{
				$this->optind++;
				continue;
			}
			if(!strcmp($arg, '-'))
			{
				$this->optind++;
				continue;
			}
			if(substr($arg, 1, 1) == '-')
			{
				$arglen = 1;
				$arg = substr($arg, 2);
				$x = explode('=', $arg, 2);
				$longopt = $x[0];
				$optname = '--' . $longopt;
				if(isset($x[1]))
				{
					$this->optarg = $x[1];
				}
				else
				{
					$this->optarg = null;
				}
				if(isset($this->options[$longopt]))
				{
					$this->longopt =& $this->options[$longopt];
				}
				else
				{
					if($this->opterr)
					{
						echo "unrecognized option: `--$longopt'\n";
					}
					return '?';
				}
			}
			else
			{
				$ch = substr($arg, 1, 1);
				$optname = '-' . $ch;
				$arglen = 0;
				foreach($this->options as $k => $opt)
				{
					if(isset($opt['value']) && !strcmp($opt['value'], $ch))
					{
						$this->longopt =& $this->options[$k];
						break;
					}
				}
				if($this->longopt === null)
				{
					if($this->opterr)
					{
						echo "unrecognized option `-$ch'\n";
					}
					return '?';
				}
				if(!empty($this->longopt['has_arg']))
				{
					if(strlen($arg) > 2)
					{
						$this->optarg = substr($arg, 2);
						$arglen = 1;
					}
				}
				if(!$arglen)
				{
					if(strlen($arg) > 2)
					{
						$args[$this->optind] = '-' . substr($arg, 2);
					}
					else
					{
						$arglen++;
					}
				}
			}
			if($this->optarg === null)
			{
				if(!empty($this->longopt['has_arg']))
				{
					$c = $this->optind;
					if($c + 1 < count($args))
					{
						if(substr($args[$c + 1], 0, 1) != '-')
						{
							$this->optarg = $args[$c + 1];
							$arglen++;
						}
					}					
					if($this->optarg === null && $this->longopt['has_arg'] == self::required_argument)
					{
						if($this->opterr)
						{
							echo "option `$optname' requires an argument\n";
						}
						return '?';
					}
				}
			}
			else if(empty($this->longopt['has_arg']))
			{
				if($this->opterr)
				{
					echo "option `$optname' doesn't allow an argument\n";
				}
				return '?';
			}			
			if($this->optarg === null)
			{
				$this->longopt['flag'] = true;		
			}
			else
			{
				$this->longopt['flag'] = $this->optarg;
			}
			if(isset($this->longopt['value']))
			{
				$this->optopt = $this->longopt['value'];
			}
			else
			{
				$this->optopt = $optname;
			}
			if($arglen)
			{
				array_splice($args, $this->optind, $arglen);
			}
			return $this->optopt;
		}
		return -1;
	}

	protected function err($message)
	{
		$args = func_get_args();
		$this->request->err('Error: ' . implode(' ', $args) . "\n");
	}
}
