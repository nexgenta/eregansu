<?php

/* Eregansu: Session handling
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

/* Configuration defines:
 *
 *   SESSION_PARAM_NAME         Name of the URL parameter containing session ID
 *   SESSION_FIELD_NAME         Name of the form field containing session ID
 *   SESSION_COOKIE_NAME        Name of the HTTP cookie containing session ID
 *   SESSION_COOKIE_DOMAIN      Name of the DNS domain used when setting cookie
 *
 * Additionally, as this implementation is based upon PHP’s built-in session
 * module, its configuration settings will also apply here.
 */

class Session
{
	protected $data = array();
	protected $paramName = 'sid';
	protected $fieldName = 'sid';
	protected $cookieName = 'sid';
	protected $cookiePath = '/';
	protected $hostname = null;
	protected $open = false;
	protected $id = null;
	
	public function __construct($req)
	{
//		syslog(LOG_CRIT, "---- Session::__construct(" . $_SERVER["REQUEST_URI"] . ") -----");
		if(defined('SESSION_PARAM_NAME')) $this->paramName = SESSION_PARAM_NAME;
		if(defined('SESSION_FIELD_NAME')) $this->fieldName = SESSION_FIELD_NAME;
		if(defined('SESSION_COOKIE_NAME')) $this->cookieName = SESSION_COOKIE_NAME;
		if(defined('SESSION_COOKIE_DOMAIN')) $this->hostname = SESSION_COOKIE_DOMAIN;
		$this->init($req);
	}
	
	protected function init($req)
	{
		ini_set('session.auto_start', 0);
		ini_set('session.name', 'eregansu');
		ini_set('session.use_cookies', 0);
		ini_set('session.use_trans_sid', 0);
	
		$sid = null;
		if(strlen($this->cookieName) && isset($_COOKIE[$this->cookieName]))
		{
			$sid = $_COOKIE[$this->cookieName];
			if(!$this->sessionExists($sid))
			{
				$sid = null;
			}
		}
		if(!$sid && strlen($this->fieldName) && isset($req->postData[$this->fieldName]))
		{
			$sid = $req->postData[$this->fieldName];
		}
		if(!$sid && strlen($this->paramName) && isset($req->query[$this->paramName]))
		{	
			$sid = $req->query[$this->paramName];
		}
		$this->beginSession($sid);
	}
	
	protected function sessionExists($id)
	{
		if(!strlen($id)) return false;
		session_id($id);
		@session_start();
		if(!empty($_SESSION['sid']) && $_SESSION['sid'] == $id)
		{
//			syslog(LOG_CRIT, "Session::sessionExists(): id " . $id . " exists");	
			return true;
		}
//		syslog(LOG_CRIT, "Session::sessionExists(): id " . $id . " does not exist");	
		@session_destroy();
		return false;
	}
	
	protected function beginSession($id)
	{
		if(!$this->sessionExists($id))
		{
			$id = null;
		}
		if(!$id && session_id())
		{
			session_destroy();
		}
		$id = session_id();
		if(!$id)
		{
			session_start();
			$id = session_id();
		}
		$this->id = $id;
		$this->open = true;
		$_SESSION['sid'] = $id;
//		syslog(LOG_CRIT, "Session::beginSession(): id = " . $this->id);		
		$this->data =& $_SESSION;
		if(!isset($this->data['started']))
		{
			$this->data['started'] = gmstrftime('%Y-%m-%d %H:%M:%S');
		}
		if(!isset($this->data['nonce']))
		{
			$n = '';
			for($c = 0; $c < 16; $c++) $n .= rand(0, 9);
			$this->data['nonce'] = $n;
		}
		if(strlen($this->paramName))
		{
			$this->data['usid'] = ';' . $this->paramName . '=' . urlencode($this->data['sid']);
			$this->data['qusid'] = '?' . $this->paramName . '=' . urlencode($this->data['sid']);
		}
		if(strlen($this->fieldName))
		{
			$this->data['fieldName'] = $this->fieldName;
		}
		$this->commit();
	}
	
	protected function setCookie()
	{
		if(strlen($this->cookieName))
		{
			setcookie($this->cookieName, $this->data['sid'], 0, $this->cookiePath, $this->hostname);
		}
	}
	
	public function commit()
	{
		if($this->open)
		{
//			syslog(LOG_CRIT, "Session::commit(): id = " . $this->id);		
			session_write_close();
			$this->setCookie();
		}
		else
		{
//			syslog(LOG_CRIT, "Session::commit(): Attempt to commit when session is closed");
		}
		$this->open = false;
	}
	
	public function begin()
	{
		if(!$this->open)
		{
//			syslog(LOG_CRIT, "Session::begin(): id = " . $this->id);
			session_id($this->id);
			session_start();
			$this->data =& $_SESSION;
/*			if(isset($this->data['sid']))
			{
				syslog(LOG_CRIT, "Session::begin(): + sid = " . $this->data['sid']);
			}
			else
			{
				syslog(LOG_CRIT, "Session::begin(): + sid is unset");
			} */
		}
/*		else
		{
			syslog(LOG_CRIT, "Session::open(): Attempt to open when session is open");		
		} */
		$this->open = true;
	}
	
	public function &__get($key)
	{
		if(isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		$null = null;
		return $null;
	}
	
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}
	
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
	
	public function __unset($key)
	{
		unset($this->data[$key]);
	}
}

class TransientSession extends Session
{
	protected function init($req)
	{
		$this->data = array();
		$this->data['sid'] = str_repeat(0, 32);
		$this->data['started'] = gmstrftime('%Y-%m-%d %H:%M:%S');
		$this->data['usid'] = '';
		$this->data['qusid'] = '';
		$this->data['fieldName'] = 'sid';
		$this->data['nonce'] = 1;
	}
	
	public function commit()
	{
	}
	
	public function begin()
	{
	}
	
	protected function setCookie()
	{
	}
}