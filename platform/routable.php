<?php

/* Eregansu: Classes which can process requests
 *
 * Copyright 2009 Mo McRoberts.
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
 * @framework Eregansu
 */

interface IRequestProcessor
{
	public function process(Request $req);
}

class Routable implements IRequestProcessor
{
	protected $model;
	protected $modelClass = null;
	protected $modelArgs = null;
	protected $crumbName = null;
	protected $crumbClass = null;
	
	public function __construct()
	{
		if($this->modelClass)
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
		$page = new Error($code);
		$page->object = $object;
		$page->detail = $detail;
		return $page->process($req);
	}
}

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

class Router extends Routable
{
	protected $sapi = array('http' => array(), 'cli' => array());
	protected $routes;
	
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
	
	public function routeInstance(Request $req, $route)
	{
		global $MODULE_ROOT;
		
		if(!is_array($route))
		{
			return $this->error(Error::NOT_IMPLEMENTED, $req);		
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
		if(isset($route['file']))
		{
			$f = $route['file'];
			if(isset($route['name']) && empty($route['adjustBase']) && empty($route['adjustModuleBase']))
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
		if(!isset($route['class']) || !class_exists($route['class']))
		{
			$req->err('Class ' . $route['class'] . ' is not implemented in ' . get_class($this) . "\n");
			return $this->error(Error::NOT_IMPLEMENTED, $req);
		}
		$target = new $route['class']();
		if(!$target instanceof IRequestProcessor)
		{
			return $this->error(Error::ROUTE_NOT_PROCESSOR, $req);
		}
		return $target;		
	}
	
	protected function unmatched(Request $req)
	{
		return $this->error(Error::ROUTE_NOT_MATCHED, $req);	
	}
}

class App extends Router
{
	public $parent;
	public $skin;

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
				require_once($MODULE_ROOT . constant('_MODULE_CLASS_PATH'));
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
		parent::process($req);
		$req->app = $this->parent;
		$this->parent = null;	
	}
}

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

/* Route requests to a particular app based upon a domain name */
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


class Proxy extends Router
{
	public static $willPerformMethod;

	public $request;
	protected $supportedTypes = array();
	protected $supportedMethods = array('GET','HEAD');
	protected $noFallThroughMethods = array('GET', 'HEAD', '__CLI__', '__MQ__');
	protected $object = null;
	protected $sessionObject = null;
		
	protected function unmatched(Request $req)
	{
		$this->request = $req;
		$method = $req->method;
		if($req->method == 'POST' && isset($req->postData['__method']))
		{
			$method = $req->postData['__method'];
		}
		if(!$this->getObject())
		{
			$this->request = null;
			$this->sessionObject = null;
			return false;
		}		
		if(!in_array($method, $this->supportedMethods))
		{
			$req->method = $method;
			$req->err('Method ' . $method . ' is not supported by ' . get_class($this) . "\n");
			return $this->error(Error::METHOD_NOT_ALLOWED);
		}
		$type = null;
		foreach($req->types as $atype)
		{
			if(in_array($atype, $this->supportedTypes))
			{
				$type = $atype;
				break;
			}
		}
		if($type == null)
		{
			if(in_array('*/*', $req->types))
			{
				foreach($this->supportedTypes as $atype)
				{
					$type = $atype;
					break;
				}
			}
		}
		if($type == null)
		{
			return $this->error(Error::TYPE_NOT_SUPPORTED);
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
			$req->err('Method ' . $methodName . ' is not implemented by ' . get_class($this) . "\n");
			return $this->error(Error::METHOD_NOT_IMPLEMENTED);
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
				return $this->perform_GET_XML();
			case 'application/json':
				return $this->perform_GET_JSON();
			case 'application/rdf+xml':
				return $this->perform_GET_RDF();
			case 'application/x-yaml':
				return $this->perform_GET_YAML();
			case 'application/atom+xml':
				return $this->perform_GET_Atom();
			case 'text/plain':
				return $this->perform_GET_Text();
		}	
	}
	
	protected function perform_GET_XML()
	{
		$this->request->header('Content-type', 'text/xml; charset=UTF-8');
	}
	
	protected function perform_GET_Text()
	{
		$this->request->header('Content-type', 'text/plain; charset=UTF-8');
	}

	protected function perform_GET_JSON()
	{
		$this->request->header('Content-type', 'application/json');
		$this->request->flush();
		if(isset($this->object))
		{
			echo json_encode($this->object);
		}
		else if(isset($this->objects))
		{
			echo json_encode($this->objects);
		}
	}

	protected function perform_GET_RDF()
	{
	}
	
	protected function perform_GET_YAML()
	{
	}
	
	protected function perform_GET_Atom()
	{
	}

	protected function perform_POST($type)
	{
		return $this->error(Error::UNSUPPORTED_MEDIA_TYPE);
	}

	protected function perform_PUT($type)
	{
		switch($this->request->contentType)
		{
			case 'application/x-www-form-urlencoded':
			case 'multipart/form-data':
				return $this->putObject($this->request->postData);
			case 'application/json':
				if(($data = file_get_contents('php://input')))
				{
					$data = @json_decode($data, true);
					if($data == null)
					{
						return $this->error(Error::BAD_REQUEST);
					}
					return $this->putObject($data);
				}
				return $this->error(Error::BAD_REQUEST);
		}
		return $this->error(Error::UNSUPPORTED_MEDIA_TYPE);
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

/* A helper command-line-only proxy class */
interface ICommandLine
{
	function main($args);
}

abstract class CommandLine extends Proxy implements ICommandLine
{
	protected $supportedMethods = array('__CLI__');
	protected $supportedTypes = array('text/plain');
	protected $args;
	
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
		if(0 == $this->main($this->args))
		{
			return true;
		}
		return false;
	}
	
	protected function checkargs(&$args)
	{
		return true;
	}
}
