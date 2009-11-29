<?php

/* Eregansu: Authentication
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

if(!defined('DEFAULT_AUTH_SCHEME')) define('DEFAULT_AUTH_SCHEME', 'https');

interface IAuthEngine
{
	public function verifyAuth($request, $scheme, $remainder, $authData, $callbackIRI);
	public function verifyToken($request, $scheme, $remainder, $token);
	public function refreshUserData(&$data);
	public function callback($request, $scheme, $remainder);
	public function retrieveUserData($scheme, $remainder);	
}

class AuthError extends Exception
{
	public $reason;
	public $engine;
	
	public function __construct($engine, $message = null, $reason = null)
	{
		if(!strlen($message)) $message = 'Incorrect sign-in name or password';
		if(!strlen($reason)) $reason = $message;
		parent::__construct($message);
		$this->reason = $reason;
		$this->engine = $engine;
	}
}

/* Base class for authentication engines */

abstract class Auth implements IAuthEngine
{
	protected static $authEngines = array();
	protected $id = null;
	protected $builtinAuthScheme = false;
	
	/* Create an instance of an authentication system given an IRI.
	 * The instance is returned by the call to Auth::authEngineForScheme().
	 * $iri will be modified to strip the scheme (if supplied), which will be
	 * stored in $scheme. Thus, upon successful return, a fully-qualified
	 * IRI can be constructed from $scheme . ':' . $iri
	 */
	public static function authEngineForIRI(&$iri, &$scheme, $defaultScheme = null)
	{
		if(!strlen($defaultScheme)) $defaultScheme = DEFAULT_AUTH_SCHEME;
		$c = strpos($iri, ':');
		$s = strpos($iri, '/');
		$a = strpos($iri, '@');
		if($c !== false && $s !== false && $s < $c) $c = false;
		if($c !== false && $a !== false && $a < $c) $c = false;
		if($c !== false)
		{
			$scheme = substr($iri, 0, $c);
			$iri = substr($iri, $c + 1);
		}
		else
		{
			$scheme = $defaultScheme;
		}
		return self::authEngineForScheme($scheme);
	}
		
	/* Return an instance of an authentication system given a token name
	 * Analogous to Auth::authEngineForIRI(), except operating on tokens rather
	 * than IRIs. Unlike IRIs, tokens use plings as a separator
	 * rather than colons, so upon successful return a fully-qualified
	 * token name can be constructed from $scheme . '!' . $tokenName
	 */
	public static function authEngineForToken(&$tokenName, &$scheme)
	{
		if(($p = strpos($tokenName, '!')) !== false)
		{
			$tokenName = substr($tokenName, 0, $p) . ':' . substr($tokenName, $p + 1);
		}
		return self::authEngineForIRI($tokenName, $scheme);
	}
	
	/* Return an instance of an authentication system for a given named scheme */
	public static function authEngineForScheme($scheme)
	{
		if(!isset(self::$authEngines[$scheme]))
		{
			switch($scheme)
			{
				case 'builtin':
					self::$authEngines[$scheme] = new BuiltinAuth();
					break;
				case 'http':
				case 'https':
				case 'openid':
					require_once(dirname(__FILE__) . '/openid.php');
					$scheme = 'openid';
					self::$authEngines[$scheme] = new OpenIDAuth();
					break;
				case 'posix':
					require_once(dirname(__FILE__) . '/posix.php');
					self::$authEngines[$scheme] = new PosixAuth();
					break;
				default:
					return null;
			}
		}
		return self::$authEngines[$scheme];
	}
	
	public function __construct()
	{
		if(defined('IDENTITY_IRI'))
		{
			require_once(dirname(__FILE__) . '/id.php');
			$this->id = Identity::getInstance();
		}
	}
		
	/* Given a request, a scheme, the remainder of an IRI and some
	 * (authentication-system specific) data, return true or false
	 * depending upon whether authentication was successful or not.
	 */
	public function verifyAuth($request, $scheme, $remainder, $authData, $callbackIRI)
	{
		return new AuthError($this);
	}
	
	/* Attempt to validate an authentication token for a user */
	public function verifyToken($request, $scheme, $tokenName, $token)
	{
		return new AuthError($this);
	}
	
	/* Continue a multi-stage login process */
	public function callback($request, $scheme, $remainder)
	{
		return new AuthError($this);
	}
	
	/* Retrieve (creating if they don’t exist, if possible) user details given
	 * a particular IRI.
	 *
	 */
	protected function createRetrieveUserWithIRI($iri, $data = null)
	{
		if(!is_array($iri))
		{
			$iri = array($iri);
		}
		if($this->id)
		{
			foreach($iri as $i)
			{
				if(($uuid = $this->id->uuidFromIRI($i, $data)))
				{
					return $uuid;
				}
			}
			foreach($iri as $i)
			{
				if(($uuid = $this->id->createIdentity($i, $data, true)))
				{
					return $uuid;
				}
			}
		}
		else if(isset($data['uuid']) && $this->builtinAuthScheme)
		{
			return $data['uuid'];
		}
		return null;
	}
	
	/* Called periodically by the session-initiated callback in platform.php
	 * to cause the user details in the session to be updated by the
	 * authentication and identity layers.
	 */
	public function refreshUserData(&$data)
	{
		if($this->id)
		{
			$this->id->refreshUserData($data);
		}
		if(!isset($data['ttl']))
		{
			$data['ttl'] = time(0) + 30;	
		}
	}
	
	/* Given a scheme/remainder pair, retrieve the user details known to
	 * the authentication layer.
	 *
	 * Note that this function will NOT invoke refreshUserData(), above.
	 * It is the caller’s responsibility to do this if manually associating
	 * a user with a session.
	 */
	public function retrieveUserData($scheme, $remainder)
	{
		return null;
	}
	
}

/* Authentication system providing the builtin: scheme, which authenticates
 * users listed in the $BUILTIN_USERS global array.
 */
class BuiltinAuth extends Auth
{
	protected $builtinAuthScheme = true;
	
	public function verifyAuth($request, $scheme, $iri, $authData, $callbackIRI)
	{
		global $BUILTIN_USERS;
		
		if(!isset($BUILTIN_USERS[$iri]))
		{
			return new AuthError($this, null, 'User ' . $iri . ' does not exist');
		}
		if(!strcmp($BUILTIN_USERS[$iri]['password'], crypt($authData, $BUILTIN_USERS[$iri]['password'])))
		{
			$user = $BUILTIN_USERS[$iri];
			if(!isset($user['scheme'])) $user['scheme'] = $scheme;
			if(!isset($user['iri'])) $user['iri'] = $scheme . ':' . $iri;
			if(!($uuid = $this->createRetrieveUserWithIRI($scheme . ':' . $iri, $user)))
			{
				return new AuthError($this, 'You cannot log into your account at this time.', 'Identity/authorisation failure');
			}
			$user['uuid'] = $uuid;
			$this->refreshUserData($user);
			return $user;
		}
		return new AuthError($this, null, 'Incorrect password supplied for user ' . $iri);
	}
	
	public function verifyToken($request, $scheme, $iri, $token)
	{
		global $BUILTIN_USERS;
		
		if(!isset($BUILTIN_USERS[$iri]))
		{
			return new AuthError($this, null, 'User ' . $iri . ' does not exist');
		}
		if(!strcmp($BUILTIN_USERS[$iri]['password'], crypt($token, $BUILTIN_USERS[$iri]['password'])))
		{
			$user = $BUILTIN_USERS[$iri];
			$this->refreshUserData($user);
			return $user;
		}
		return new AuthError($this, null, 'Incorrect password (as a token) supplied for user ' . $iri);
	}
	
	public function retrieveUserData($scheme, $remainder)
	{
		global $BUILTIN_USERS;
		
		if(isset($BUILTIN_USERS[$remainder]))
		{
			return $BUILTIN_USERS[$remainder];
		}
		return null;
	}

	
	public function refreshUserData(&$data)
	{
		if(!isset($data['scheme'])) $data['scheme'] = 'builtin';
		$data['iri'] = 'builtin' . ':' . $data['name'];
		$data['ttl'] = time(0) + 1; /* Force rapid refresh when we’re dealing with static config */
		parent::refreshUserData($data);
	}
}
