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
	public function verifyAuth($request, $scheme, $iri, $authData);
	public function verifyToken($request, $scheme, $iri, $token);
	public function refreshUserData(&$data);	
}

/* Base class for authentication engines */

abstract class Auth implements IAuthEngine
{
	protected static $authEngines = array();
	
	/* Create an instance of an authentication system given an IRI.
	 * The instance is returned by the call to Auth::authEngineForIRI().
	 * $iri will be modified to strip the scheme (if supplied), which will be
	 * stored in $scheme. Thus, upon successful return, a fully-qualified
	 * IRI can be constructed from $scheme . ':' . $iri
	 */
	public static function authEngineForIRI(&$iri, &$scheme)
	{
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
			$scheme = DEFAULT_AUTH_SCHEME;
		}
		return self::authEngineForScheme($scheme);
	}
	
	/* Return an instance of an authentication system given a token name
	 * Analogous to AuthauthEngineForIRI(), except operating on tokens rather
	 * than APIs. Unlike IRIs, tokens use plings as a separator
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
				default:
					return null;
			}
		}
		return self::$authEngines[$scheme];
	}
	
	/* Given a request, a scheme, the remainder of an IRI and some
	 * (authentication-system specific) data, return true or false
	 * depending upon whether authentication was successful or not.
	 */
	public function verifyAuth($request, $scheme, $remainder, $authData)
	{
		return false;
	}
	
	/* Attempt to validate an authentication token for a user */
	public function verifyToken($request, $scheme, $tokenName, $token)
	{
		return false;
	}
}

/* Authentication system providing the builtin: scheme, which authenticates
 * users listed in the $BUILTIN_USERS global array.
 */
class BuiltinAuth extends Auth
{
	public function verifyAuth($request, $scheme, $iri, $authData)
	{
		global $BUILTIN_USERS;
		
		if(!isset($BUILTIN_USERS[$iri]))
		{
			return false;
		}
		if(!strcmp($BUILTIN_USERS[$iri]['password'], crypt($authData, $BUILTIN_USERS[$iri]['password'])))
		{
			$user = $BUILTIN_USERS[$iri];
			$this->refreshUserData($user);
			return $user;
		}
		return false;
	}
	
	public function verifyToken($request, $scheme, $iri, $token)
	{
		global $BUILTIN_USERS;
		
		if(!isset($BUILTIN_USERS[$iri]))
		{
			return false;
		}
		if(!strcmp($BUILTIN_USERS[$iri]['password'], crypt($token, $BUILTIN_USERS[$iri]['password'])))
		{
			$user = $BUILTIN_USERS[$iri];
			$this->refreshUserData($user);
			return $user;
		}
		return false;	
	}
	
	public function refreshUserData(&$data)
	{
		if(!isset($data['scheme'])) $data['scheme'] = 'builtin';
		$data['iri'] = 'builtin' . ':' . $data['name'];
		$data['ttl'] = time(0) + 60;
	}
}
