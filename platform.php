<?php

/* Eregansu - A lightweight web application platform
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
 * @framework Eregansu
 */

/* Define our version of uses() before including the core library - this will
 * take precedence.
 */
 
function uses()
{
	global $APP_ROOT;
	static $_lib_modules = array('base32', 'cli', 'date', 'db', 'execute', 'form', 'ldap', 'mime', 'request', 'session', 'url', 'uuid');
	
	$_modules = func_get_args();
	foreach($_modules as $_mod)
	{
		if(in_array($_mod, $_lib_modules))
		{
			require_once(PLATFORM_ROOT . 'lib/' . $_mod . '.php');
		}
		else
		{
			require_once(PLATFORM_ROOT . 'platform/' . $_mod . '.php');
		}
	}
}

/* Initialise the core library */
require_once(dirname(__FILE__) . '/lib/common.php');

/* Load the application-wide and per-instance configurations */
require_once(CONFIG_ROOT . 'config.php');
require_once(CONFIG_ROOT . 'appconfig.php');

/* Load the initial set of modules */
require_once(PLATFORM_ROOT.  'lib/request.php');
require_once(PLATFORM_ROOT . 'lib/session.php');
require_once(PLATFORM_ROOT . 'lib/url.php');
require_once(PLATFORM_ROOT . 'platform/routable.php');
require_once(PLATFORM_ROOT . 'platform/page.php');
require_once(PLATFORM_ROOT . 'platform/template.php');
require_once(PLATFORM_ROOT . 'platform/error.php');

/* Our global event sink: at the moment just used to implement a callback from
 * the request class which fires when the session has been initialised.
 */

/**
 * @class PlatformEventSink
 * @internal
 * @brief Class containing callbacks registered by the platform itself
 */
 
class PlatformEventSink
{
	public static function sessionInitialised($req, $session)
	{
		if(isset($session->user))
		{
			if(empty($session->user['ttl']) || $session->user['ttl'] < time())
			{
				uses('auth');
				$session->begin();
				if(($engine = Auth::authEngineForScheme($session->user['scheme'])))
				{
					$engine->refreshUserData($session->user);
				}
				else
				{
					unset($session->user);
				}
				$session->commit();
			}
		}
		else if($req->sapi != 'http')
		{
			uses('auth');
			$engine = Auth::authEngineForScheme('posix');
			if(($data = $engine->retrieveUserData('posix', posix_geteuid())))
			{
				$session->begin();
				$session->user = $data;
				$session->commit();
			}
		}
	
	}
}

URL::register();

/* Create an instance of the request class */
$request = Request::requestForSAPI();
$request->sessionInitialised = array('PlatformEventSink', 'sessionInitialised');

/* Create the initial app instance */
$app = App::initialApp();
