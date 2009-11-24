<?php

/* Eregansu: Classes which can process requests
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


class Routable implements IRequestProcessor
{
	protected $model;
	protected $modelClass = null;
	protected $modelArgs = null;
	protected $crumbName = null;

	public function __construct()
	{
		if($this->modelClass)
		{
			$this->model = call_user_func(array($this->modelClass, 'getInstance'), $this->modelArgs);
		}
	}
	
	public function process($req)
	{
		if(isset($req->data['crumbName'])) $this->crumbName = $req->data['crumbName'];
		$this->addCrumb($req);
	}
	
	protected function addCrumb($req)
	{
		if($this->crumbName !== null)
		{
			$req->addCrumb($this->crumbName);
		}	
	}
	
	protected function error($code, $req = null, $object = null, $detail = null)
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
	
	public function process($req)
	{
		$targ = $this->target;
		if(substr($targ, 0, 1) == '/')
		{
			$targ = substr($targ, 1);
			if($this->useBase)
			{
				$req->redirect($req->base . $targ);
			}
			$req->redirect($req->root . $targ);
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
	
	protected function getRouteName($req, &$consume)
	{
		if(isset($req->params[0]))
		{
			$consume = true;
			return $req->params[0];
		}
		return null;
	}
	
	public function locateRoute($req)
	{
		global $APP_ROOT;
		
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
				unset($data['routes']);
				unset($data['crumbName']);
				unset($data['class']);
				$data = array_merge($data, $route);
			}
			else
			{
				$data = $route;
			}
			$data['key'] = $k;
			$req->data = $data;
			if($consume)
			{
				if(!empty($data['adjustBase']))
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
	
	public function process($req)
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
	
	protected function authoriseRoute($req, $route)
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
				header('WWW-Authenticate: basic realm="' . $req->hostname . '"');
				$p = new Error(Error::AUTHORIZATION_REQUIRED);
				return $p->process($req);
			}
		}
		return true;
	}
	
	public function routeInstance($req, $route)
	{
		global $APP_ROOT;
		
		if(!empty($route['adjustBase']))
		{
			if(isset($route['name']))
			{
				$APP_ROOT .= $route['name'] . '/';
			}
			else if(substr($route['key'], 0, 1) != '_')
			{
				$APP_ROOT .= $route['key'] . '/';
			}
		}
		if(isset($route['file']))
		{
			$f = $route['file'];
			if(isset($route['name']) && empty($route['adjustBase']))
			{
				$f = $route['name'] . '/' . $f;
			}
			if(substr($f, 0, 1) != '/')
			{
				if(!empty($route['fromRoot']))
				{
					$f = APPS_ROOT . $f;
				}
				else
				{
					$f = $APP_ROOT . $f;
				}
			}
			require_once($f);
		}
		$target = new $route['class']();
		if(!$target instanceof IRequestProcessor)
		{
			return $this->error(Error::ROUTE_NOT_PROCESSOR, $req);
		}
		return $target;		
	}
	
	protected function unmatched($req)
	{
		return $this->error(Error::ROUTE_NOT_MATCHED, $req);	
	}
}

class App extends Router
{
	public $parent;
	public $skin;

	public function __construct()
	{
		parent::__construct();
		$this->routes['login'] = array('file' => PLATFORM_ROOT . 'login/app.php', 'class' => 'LoginPage', 'fromRoot' => true);
	}
	
	public function process($req)
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
	
	protected function getRouteName($req, &$consume)
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
	public $request;
	protected $supportedTypes = array();
	protected $supportedMethods = array('GET','HEAD');
	protected $noFallThroughMethods = array('GET', 'HEAD', '__CLI__', '__MQ__');
	protected $object = null;
	protected $sessionObject = null;
		
	protected function unmatched($req)
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
		$methodName = 'perform_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $method);
		if(!method_exists($this, $methodName))
		{
			return $this->error(Error::METHOD_NOT_IMPLEMENTED);
		}
		$r = $this->$methodName($type);
		if($r && !in_array($method, $this->noFallThroughMethods))
		{
			$r = $this->perform_GET($type);
		}
		$this->object = null;
		$this->request = null;
		$this->sessionObject = null;
		return $r;
	}
	
	protected function error($code, $req = null, $object = null, $detail = null)
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
		}	
	}
	
	protected function perform_GET_XML()
	{
		header('Content-type: text/xml; charset=UTF-8');
	}

	protected function perform_GET_JSON()
	{
		header('Content-type: application/json');
		echo json_encode($this->object);
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
class CommandLine extends Proxy
{
	protected $supportedMethods = array('__CLI__');
	protected $supportedTypes = array('text/plain');
}
