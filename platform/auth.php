<?php

/* Eregansu: Authentication
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
 * @year 2009
 * @include uses('auth');
 * @since Available in Eregansu 1.0 and later. 
 */

uses('uri');

URI::register('builtin', 'AuthEngine', array('file' => dirname(__FILE__) . '/auth/builtin.php', 'class' => 'BuiltinAuth'));
URI::register('http', 'AuthEngine', array('file' => dirname(__FILE__) . '/auth/openid.php', 'class' => 'OpenIDAuth'));
URI::register('https', 'AuthEngine', array('file' => dirname(__FILE__) . '/auth/openid.php', 'class' => 'OpenIDAuth'));
URI::register('posix', 'AuthEngine', array('file' => dirname(__FILE__) . '/auth/posix.php', 'class' => 'PosixAuth'));

if(!defined('DEFAULT_AUTH_SCHEME')) define('DEFAULT_AUTH_SCHEME', 'https');

/**
 * Interface implemented by authentication engines.
 */
interface IAuthEngine
{
	public function verifyAuth($request, $scheme, $remainder, $authData, $callbackIRI);
	public function verifyToken($request, $scheme, $remainder, $token);
	public function refreshUserData(&$data);
	public function callback($request, $scheme, $remainder);
	public function retrieveUserData($scheme, $remainder);	
}

/**
 * Exception class whose instances are thrown when an authentication exception
 * occurs.
 *
 * @synopsis throw new AuthError($engine, $message, $reason);
 */
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
	
	/** Create an instance of an authentication system given an IRI.
	 *
	 * The instance is returned by the call to \m{Auth::authEngineForScheme}.
	 * \p{$iri} will be modified to strip the scheme (if supplied), which will
	 * be stored in \p{$scheme}. Thus, upon successful return, a fully-qualified
	 * IRI can be constructed from \x{$scheme . ':' . $iri}
	 *
	 * @param[in,out] string $iri The IRI to match against
	 * @param[out] string $scheme The authentication IRI scheme that was
	 *   determined
	 * @param[in] string $defaultScheme The default authentication scheme to
	 *   use if none can be determined from \p{$iri}
	 * 
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
		global $AUTH_CLASSES;
		
		if(!isset(self::$authEngines[$scheme]))
		{
			if(isset($AUTH_CLASSES[$scheme]))
			{
				$class = $AUTH_CLASSES[$scheme];
				self::$authEngines[$scheme] = call_user_func(array($class, 'getInstance'));
				return self::$authEngines[$scheme];
			}
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

