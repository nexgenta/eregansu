<?php

/* Copyright 2009-2012 Mo McRoberts.
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
 * @package EregansuLib Eregansu Core Library
 * @year 2009-2011
 * @include require_once(dirname(__FILE__) . '/../eregansu/lib/common.php');
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
 * Indicates that the Eregansu framework is loaded.
 *
 * In a future version, __EREGANSU__ will be defined to a release tag
 * or commit hash, substituted by an installation script. The
 * percent-percent-version-percent-percent comment is a placeholder
 * to indicate which line should be replaced.
 */
define('__EREGANSU__', 'master'); /* %%version%% */

if(!defined('PHP_VERSION_ID'))
{
    $php_version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($php_version[0] * 10000 + $php_version[1] * 100 + $php_version[2]));
}

/**
 * The ISerialisable interface is implemented by classes which can serialise
 * themselves.
 */
interface ISerialisable
{
	public function serialise(&$mimeType, $returnBuffer = false, $request = null, $sendHeaders = null /* true if (!$returnBuffer && $request) */);
}

if(defined('EREGANSU_MINIMAL_CORE'))
{
	return true;
}

/**
 * Determine whether an object or array is traversable as an array
 *
 * The \f{is_arrayish} function is analogous to PHP’s \f{is_array} function, except
 * that it also returns \c{true} if \p{$var} is an instance of a class implementing
 * the \c{Traversable} interface.
 *
 * @type bool
 * @param[in] mixed $var A variable to test
 * @return \c{true} if \p{$var} can be traversed using \f{foreach}, \c{false} otherwise
 */
function is_arrayish($var)
{
	return is_array($var) || (is_object($var) && $var instanceof Traversable);
}

/**
 * Parse a string and return the boolean value it represents
 *
 * @type bool
 * @param[in] string $str a string representation of a boolean value
 * @return The boolean value \p{$str} represents
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
 * @type void
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
 * @type string
 * @param[in] string $str The string to HTML-escape
 * @return The escaped version of \p{$str}
 */
 
function _e($str)
{
	return str_replace('&apos;', '&#39;', str_replace('&quot;', '&#34;', htmlspecialchars(strval($str), ENT_QUOTES, 'UTF-8')));
}

/**
 * Write text to the output stream, followed by a newline.
 * @return void
 * @varargs
 */

function writeLn()
{
	$args = func_get_args();
	echo implode(' ', $args) . "\n";
}


if(!function_exists('uses')) {

	/**
	 * Include one or more Eregansu modules
	 *
	 * The \f{uses} function loads one or more Eregansu modules. You can specify as
	 * many modules as are needed, each as a separate parameter.
	 *
	 * @type void
	 * @param[in] string $module,... The name of a module to require. For example, \l{base32}.
	 */

	function uses($module)
	{
		static $_map = array('url' => 'uri', 'xmlns' => 'uri');

		$_modules = func_get_args();
		foreach($_modules as $_mod)
		{
			$_mod = isset($_map[$_mod]) ? $_map[$_mod] : $_mod;
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
	if(defined('EREGANSU_STRICT_ERROR_HANDLING') &&
	   ($errno & (E_WARNING|E_USER_WARNING|E_NOTICE|E_USER_NOTICE)))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		exit(1);
	}		
	if($errno & (E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_USER_ERROR|E_RECOVERABLE_ERROR))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		exit(1);
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

function exception_handler($exception)
{
	if(php_sapi_name() != 'cli') echo '<pre>';
	echo "Uncaught exception:\n\n";
	echo $exception . "\n";
	die(1);
}

function assertion_handler()
{
	throw new ErrorException('Assertion failed');
	die(1);
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
set_exception_handler('exception_handler');
assert_options(ASSERT_QUIET_EVAL, true);
assert_options(ASSERT_CALLBACK, 'assertion_handler');

if(!defined('PUBLIC_ROOT'))
{
	define('PUBLIC_ROOT', defined('INSTANCE_ROOT') ? INSTANCE_ROOT : ((isset($_SERVER['SCRIPT_FILENAME']) ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : realpath(dirname(__FILE__ ) . '/../../')) . '/'));
}
if(!defined('INSTANCE_ROOT'))
{
	define('INSTANCE_ROOT', PUBLIC_ROOT);
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
if(!defined('MODULES_ROOT'))
{
	if(defined('MODULES_PATH'))
	{
		define('MODULES_ROOT', INSTANCE_ROOT . MODULES_PATH . '/');
	}
	else
	{
		define('MODULES_ROOT', INSTANCE_ROOT . 'app/');
	}
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
 
$AUTOLOAD = array(
	'asn1' => dirname(__FILE__) . '/asn1.php',
	'base32' => dirname(__FILE__) . '/base32.php',
	'clirequest' => dirname(__FILE__) . '/cli.php',
	'csvimport' => dirname(__FILE__) . '/csv-import.php',
	'curl' => dirname(__FILE__) . '/curl.php',
	'curlcache' => dirname(__FILE__) . '/curl.php',  
	'dbcore' => dirname(__FILE__) . '/model.php',
	'dbschema' => dirname(__FILE__) . '/dbschema.php',
	'mime' => dirname(__FILE__) . '/mime.php',	
	'rdf' => dirname(__FILE__) . '/rdf.php',
	'rdfxmlstreamparser' => dirname(__FILE__) . '/rdfxmlstream.php',
	'request' => dirname(__FILE__) . '/request.php',
	'httprequest' => dirname(__FILE__) . '/request.php',
	'session' => dirname(__FILE__) . '/session.php',
	'searchengine' => dirname(__FILE__) . '/searchengine.php',
	'searchindexer' => dirname(__FILE__) . '/searchengine.php',
	'uri' => dirname(__FILE__) . '/uri.php',
	'url' => dirname(__FILE__) . '/uri.php',
	'uuid' => dirname(__FILE__) . '/uuid.php',
	'xmlparser' => dirname(__FILE__) . '/xml.php',
	'xmlns' => dirname(__FILE__) . '/uri.php',
	);

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
