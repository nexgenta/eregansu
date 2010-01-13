<?php

if(php_sapi_name() != 'cli')
{
	die('Error: index.setup.php may only be invoked from the command-line');
}

define('INSTANCE_ROOT', realpath(getcwd()) . '/');

if(count($argv) == 1)
{
	$argv[] = 'install';
}

require(dirname(__FILE__) . '/platform.php');

$app->process($request);
