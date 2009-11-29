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

if(!defined('INSTANCE_ROOT')) define('INSTANCE_ROOT', (isset($_SERVER['SCRIPT_FILENAME']) ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : realpath(dirname(__FILE__ ) . '/..')) . '/');
if(!defined('PLATFORM_ROOT')) define('PLATFORM_ROOT', realpath(dirname(__FILE__)) . '/');
if(!defined('CONFIG_ROOT')) define('CONFIG_ROOT', INSTANCE_ROOT . 'config/');

require_once(CONFIG_ROOT . 'config.php');
require_once(CONFIG_ROOT . 'appconfig.php');

function uses()
{
	global $APP_ROOT;
	
	static $_lib_modules = array('base32', 'date', 'db', 'form', 'request', 'session', 'execute', 'mime', 'uuid');
	$_modules = func_get_args();
	foreach($_modules as $_mod)
	{
		if(in_array($_mod, $_lib_modules))
		{
			require_once(PLATFORM_ROOT . 'lib/' . $_mod . '.php');
		}
		else
		{
			require_once(PLATFORM_ROOT . $_mod . '.php');
		}
	}
}

interface IRequestProcessor
{
	public function process($req);
}

require_once(PLATFORM_ROOT . 'lib/common.php');
require_once(PLATFORM_ROOT . 'routable.php');
require_once(PLATFORM_ROOT . 'page.php');
require_once(PLATFORM_ROOT . 'template.php');
require_once(PLATFORM_ROOT . 'error.php');

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

if(defined('APPS_PATH'))
{
	define('APPS_ROOT', INSTANCE_ROOT . APPS_PATH . '/');
}
else
{
	define('APPS_ROOT', INSTANCE_ROOT . 'app/');
}

$APP_ROOT = APPS_ROOT;

if(defined('REQUEST_CLASS'))
{
	if(defined('REQUEST_PATH'))
	{
		require_once(APPS_ROOT . REQUEST_PATH);
	}
	$request = new REQUEST_CLASS;
}
else
{
	$request = Request::requestForScheme();
}

$request->init();
$request->sessionInitialised = array('PlatformEventSink', 'sessionInitialised');

if(defined('APP_CLASS'))
{
	if(defined('APP_NAME'))
	{
		$APP_ROOT .= APP_NAME . '/';
	}
	if(defined('APP_CLASS_PATH'))
	{
		require_once($APP_ROOT . APP_CLASS_PATH);
	}
	$appClass = APP_CLASS;
	$app = new $appClass;
}
else
{
	$app = new DefaultApp;
}

global $INITIAL_APP;

$INITIAL_APP = $app;
