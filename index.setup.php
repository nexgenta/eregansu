<?php

if(php_sapi_name() != 'cli')
{
	die('Error: index.setup.php may only be invoked from the command-line');
}

define('INSTANCE_ROOT', realpath(getcwd()) . '/');

require(dirname(__FILE__) . '/platform.php');

if(count($argv) == 1)
{
	$argv[] = 'setup';
}

$app->process($request);
