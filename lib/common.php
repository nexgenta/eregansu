<?php

/* Copyright 2009, 2010 Mo McRoberts.
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
 * @framework EregansuCore Eregansu Core Library
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 * @year 2010
 * @copyright Mo McRoberts
 * @include require_once('lib/common.php');
 * @sourcebase http://github.com/nexgenta/eregansu/blob/master/
 * @since Available in Eregansu 1.0 and later. 
 */

/**
 * @module lib/common.php
 * @brief Entry-point of the Eregansu Core Library.
 *
 * The Eregansu Core Library provides support facilities and base classes used by
 * the Eregansu Platform. When included, this file performs a number of initialisation
 * tasks:
 * 
 * - Sets a default umask
 * - Enables error reporting
 * - Disables magic quotes, to the extent possible
 * - Sets the default character set to UTF-8
 * - Sets the default timezone to UTC
 * - Disables session auto-start
 * - Installs an error handler which throws exceptions
 * - Installs a class auto-load handler
 * - Defines the e(), _e(), is_arrayish() and uses() functions
 *
 * Applications built upon the Eregansu Platform need not include lib/common.php directly.
 * However, an application just wishing to make use of the facilities provided by the
 * Core Library may include lib/common.php as part of its initialisation.
 */

/**
 * @brief Determine whether an object or array is traversable as an array
 *
 * The \f{is_arrayish} function is analogous to PHP’s \f{is_array} function, except
 * that it also returns \c{true} if \p{$var} is an instance of a class implementing
 * the \c{Traversable} interface.
 *
 * @param[in] mixed $var A variable to test
 * @return bool \c{true} if \p{$var} can be traversed using \f{foreach}, \c{false} otherwise
 */
function is_arrayish($var)
{
	return is_array($var) || (is_object($var) && $var instanceof Traversable);
}

/**
 * @brief Parse a string and return the boolean value it represents
 *
 * @param[in] string $str a string representation of a boolean value
 * @return bool The boolean value \p{$str} represents
 */
function parse_bool($str)
{
	$str = trim(strtolower($str));
	if($str == 'yes' || $str == 'on' || $str == 'true') return true;
	if($str == 'no' || $str == 'off' || $str == 'false') return false;
	return !empty($str);
}

/**
 * @brief HTML-escape a string and output it
 *
 * \f{e} accepts a string and outputs it after ensuring any characters which have special meaning
 * in XML or HTML documents are properly escaped.
 *
 * @param[in] string $str The string to HTML-escape
 */
function e($str)
{
	echo _e($str);
}

/**
 * @brief HTML-escape a string and return it.
 *
 * \f{_e} accepts a string and returns it after ensuring any characters which have special meaning
 * in XML or HTML documents are properly escaped. The resultant string is suitable for inclusion
 * in attribute values or element contents.
 *
 * @param[in] string $str The string to HTML-escape
 * @return string The escaped version of \p{$str}
 */
 
function _e($str)
{
	return str_replace('&quot;', '&#39;', htmlspecialchars($str));
}

if(!function_exists('uses')) {

	/**
	 * @brief Include one or more Eregansu modules
	 *
	 * The \f{uses} function loads one or more Eregansu modules. You can specify as
	 * many modules as are needed, each as a separate parameter.
	 *
	 * @param[in] string $module,... The name of a module to require. For example, \l{base32}.
	 */

	function uses($module)
	{
		$_modules = func_get_args();
		foreach($_modules as $_mod)
		{
			require_once(dirname(__FILE__) . '/' . $_mod . '.php');
		}	
	}

}

/**
 * @brief Callback handler invoked by PHP when an undefined classname is referenced
 * @internal
 */
function autoload_handler($name)
{
	global $AUTOLOAD, $AUTOLOAD_SUBST;
	
	if(isset($AUTOLOAD[strtolower($name)]))
	{
		$path = str_replace(array_keys($AUTOLOAD_SUBST), array_values($AUTOLOAD_SUBST), $AUTOLOAD[strtolower($name)]);
		require_once($path);
		return true;
	}
	return false;
}

/**
 * @brief Callback invoked by PHP when an error occurs
 * @internal
 */

function exception_error_handler($errno, $errstr, $errfile, $errline)
{
	$e = error_reporting();
	if(!$errno || ($e & $errno) != $errno) return;
	if($errno & (E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_USER_ERROR|E_RECOVERABLE_ERROR))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		exit();
	}
	return false;
}

function strict_error_handler($errno, $errstr, $errfile, $errline)
{
	$e = error_reporting();
	if(!$errno || ($e & $errno) != $errno) return;
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	return false;
}

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
set_error_handler('exception_error_handler');
if(!defined('INSTANCE_ROOT'))
{
	define('INSTANCE_ROOT', (isset($_SERVER['SCRIPT_FILENAME']) ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : realpath(dirname(__FILE__ ) . '/../../')) . '/');
}
if(!defined('PLATFORM_ROOT'))
{
	define('PLATFORM_ROOT', realpath(dirname(__FILE__) . '/..') . '/');
} 
if(!defined('PLATFORM_LIB'))
{
	define('PLATFORM_LIB', PLATFORM_ROOT . 'lib/');
}
if(!defined('PLATFORM_PATH'))
{
	define('PLATFORM_PATH', PLATFORM_ROOT . 'platform/');
}
if(!defined('CONFIG_ROOT'))
{
	define('CONFIG_ROOT', INSTANCE_ROOT . 'config/');
}
if(defined('MODULES_PATH'))
{
	define('MODULES_ROOT', INSTANCE_ROOT . MODULES_PATH . '/');
}
else
{
	define('MODULES_ROOT', INSTANCE_ROOT . 'app/');
}
$MODULE_ROOT = MODULES_ROOT;

/**
 * @var $AUTOLOAD_SUBST
 * @brief Substitutions used by the class auto-loader
 *
 * $AUTOLOAD_SUBST is an associative array of substitutions which are applied to
 * paths in $AUTOLOAD when the class auto-loader is invoked.
 *
 * By default it contains the following substitutions:
 *
 * - \c ${lib} The filesystem path to the Eregansu Core Library
 * - \c ${instance} The value of the INSTANCE_ROOT definition
 * - \c ${platform} The value of the PLATFORM_ROOT definition
 * - \c ${modules} The value of the MODULES_ROOT definition
 * - \c ${module} The filesystem path of the current module
 */
$AUTOLOAD_SUBST = array();
$AUTOLOAD_SUBST['${lib}'] = PLATFORM_LIB;
$AUTOLOAD_SUBST['${instance}'] = INSTANCE_ROOT;
$AUTOLOAD_SUBST['${platform}'] = PLATFORM_PATH;
$AUTOLOAD_SUBST['${modules}'] = MODULES_ROOT;
$AUTOLOAD_SUBST['${module}'] =& $MODULE_ROOT;

/**
 * @var $AUTOLOAD
 * @brief Mapping of class names to paths used by the class auto-loader
 *
 * $AUTOLOAD is an associative array, where the keys are all-lowercase
 * class names and the values are filesystem paths (which may contain
 * substitutions as per $AUTOLOAD_SUBST).
 *
 * When the class auto-loader is invoked, it checks the contents of
 * $AUTOLOAD and if a match is found the specified file is loaded.
 *
 * The $AUTOLOAD array is initialised with the classes which make up
 * the Eregansu Core Library.
 */
 
$AUTOLOAD = array();
$AUTOLOAD['base32'] = dirname(__FILE__) . '/base32.php';
$AUTOLOAD['clirequest'] = dirname(__FILE__) . '/cli.php';
$AUTOLOAD['dbcore'] = dirname(__FILE__) . '/db.php';
$AUTOLOAD['dbschema'] = dirname(__FILE__) . '/dbschema.php';
$AUTOLOAD['form'] = dirname(__FILE__) . '/form.php';
$AUTOLOAD['mime'] = dirname(__FILE__) . '/mime.php';
$AUTOLOAD['request'] = dirname(__FILE__) . '/request.php';
$AUTOLOAD['session'] = dirname(__FILE__) . '/session.php';
$AUTOLOAD['uuid'] = dirname(__FILE__) . '/uuid.php';

if(function_exists('spl_autoload_register'))
{
	spl_autoload_register('autoload_handler');
}
else
{
	function __autoload($name)
	{
		return autoload_handler($name);
	}
}

$VFS = array();
