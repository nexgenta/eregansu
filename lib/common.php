<?php

/* Copyright 2009 Mo McRoberts.
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

umask(007);
error_reporting(E_ALL|E_STRICT|E_RECOVERABLE_ERROR);
ini_set('display_errors', 'On');
if(function_exists('set_magic_quotes_runtime'))
{
	@set_magic_quotes_runtime(0);
}
ini_set('session.auto_start', 0);
ini_set('default_charset', 'UTF-8');
ini_set('arg_separator.output', ';');
if(function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');
if(function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
putenv('TZ=UTC');
ini_set('date.timezone', 'UTC');
function exception_error_handler($errno, $errstr, $errfile, $errline )
{
	$e = error_reporting();
	if(!$errno || ($e & $errno) != $errno) return;
	if($errno & (E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_USER_ERROR|E_RECOVERABLE_ERROR))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
	return false;
}
set_error_handler("exception_error_handler");

function is_arrayish($obj)
{
	return is_array($obj) || (is_object($obj) && $obj instanceof Traversable);
}

require_once(dirname(__FILE__) . '/request.php');
require_once(dirname(__FILE__) . '/session.php');


function e($str)
{
	echo htmlspecialchars($str);
}
function _e($str)
{
	return htmlspecialchars($str);
}

if(!function_exists('uses'))
{
	function uses()
	{
		$_modules = func_get_args();
		foreach($_modules as $_mod)
		{
			require_once(dirname(__FILE__) . '/' . $_mod . '.php');
		}	
	}
}