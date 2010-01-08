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

/**
 * @class Session
 * @brief Session handling
 *
 * The Session class implements basic session handling, based in part upon
 * PHP‘s own session support.
 *
 * The Session class overloads property access, such that values stored against
 * the session are represented as properties of the Session class.
 *
 * Before making changes to session data, you must call Session::begin(). When
 * changes have been completed, you must call Session::commit() to write the
 * session data back to the underlying storage (for example, files on disk).
 *
 * The Request class implements automatic support for the Session class: a
 * session is lazily attached (using Session::sessionForRequest()) when the
 * Request::$session property is first accessed.
 *
 * The following named constants may be defined prior to a session being attached
 * which affect the behaviour of the Session class:
 *
 * - \c SESSION_COOKIE_NAME: The name of the session cookie (defaults to \c sid)
 * - \c SESSION_COOKIE_DOMAIN: The domain name used for the session cookie (defaults to being unset)
 * - \c SESSION_PARAM_NAME: The name of the URL parameter which may contain a session ID (defaults to \c sid)
 * - \c SESSION_FIELD_NAME: The name of the form field which may contain a session ID (defaults to \c sid)
 */

class Session
{
	protected $data = array();
	protected $paramName = 'sid';
	protected $fieldName = 'sid';
	protected $cookieName = 'sid';
	protected $cookiePath = '/';
	protected $hostname = null;
	protected $open = 0;
	protected $id = null;
	
	/**
	 * @fn Session sessionForRequest($request)
	 * @brief Return a Session instance associated with a a given request
	 *
	 * @param[in] Request $request The request which should be attached to the session
	 * @returns An instance of the Sesssion class (or one of its descendants)
	 */
	public static function sessionForRequest($request)
	{
		if(defined('SESSION_CLASS'))
		{	
			$name = SESSION_CLASS;
			return new $name($request);
		}
		return new Session($request);
	}
	
	/**
	 * @fn __construct($request)
	 * @internal
	 */
	protected function __construct($request)
	{
		if(defined('SESSION_PARAM_NAME')) $this->paramName = SESSION_PARAM_NAME;
		if(defined('SESSION_FIELD_NAME')) $this->fieldName = SESSION_FIELD_NAME;
		if(defined('SESSION_COOKIE_NAME')) $this->cookieName = SESSION_COOKIE_NAME;
		if(defined('SESSION_COOKIE_DOMAIN')) $this->hostname = SESSION_COOKIE_DOMAIN;
		$this->init($request);
	}
	
	/**
	 * @internal
	 */
/*	public function __destruct()
	{
		syslog(LOG_CRIT, "Session::__destruct(): open = " . $this->open . ", id = " . $this->id);			
	} */
	
	/**
	 * @fn void init($req)
	 * @internal
	 * @brief Determine a session ID from a request, and either attach to the previous session or create a new one
	 * @param[in] Request $req The request to attach the session to and to attempt to determine a session ID from
	 */
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
	
	/**
	 * @fn bool sessionExists($id)
	 * @internal
	 * @brief Check whether the session identified by \p $id already exists
	 * @param[in] string $id The session ID to check for
	 * @returns \c true if the session identified by \p $id exists, \c false
	 */
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
	
	/**
	 * @fn void beginSession($id)
	 * @internal
	 * @param[in] string $id The session ID retrieved from the request, or \c null if it could not be determined
	 * @brief Initialises the Session instance optionally given the ID of an existing session
	 */
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
		$this->open = 1;
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
	
	/**
	 * @fn void commit()
	 * @brief Commit changes to the session data
	 *
	 * Session::commit() stores any changes which have been made to the session
	 * data so that they will be available when future requests attach to the
	 * session.
	 *
	 * @see Session::begin()
	 */
	public function commit()
	{
//		syslog(LOG_CRIT, "Session::commit(): open = " . $this->open . ", id = " . $this->id);
		if($this->open)
		{
			$this->open--;
			if(!$this->open)
			{
//				syslog(LOG_CRIT, "Session::commit(): Writing changes");
				session_write_close();
				$this->setCookie();
			}
		}
		else
		{
//			syslog(LOG_CRIT, "Session::commit(): Attempt to commit when session is closed");
		}
	}
	
	/**
	 * @fn void begin()
	 * @brief Open the session data, so that changes can be made to it
	 *
	 * Session::begin() prepares the session data for modifications. Once the
	 * modifications have been completed, you should call Session::commit().
	 *
	 * Session::begin() and Session::commit() are re-entrant: provided every
	 * call to Session::begin() has a matching call to Session::commit(), all
	 * except the outermost calls to Session::begin() and Session::commit() will
	 * have no effect.
	 */
	public function begin()
	{
//		syslog(LOG_CRIT, "Session::begin(): open = " . $this->open . ", id = " . $this->id);
		if(!$this->open)
		{
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
		$this->open++;
	}
	
	/**
	 * @internal
	 */
	public function &__get($key)
	{
		if(isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		$null = null;
		return $null;
	}
	
	/**
	 * @internal
	 */
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}
	
	/**
	 * @internal
	 */
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
	
	/**
	 * @internal
	 */
	public function __unset($key)
	{
		unset($this->data[$key]);
	}
}

/**
 * @class TransientSession
 * @brief Descendant of the Session class which has no persistent storage capabilities.
 */
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