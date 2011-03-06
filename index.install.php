<?php

if(php_sapi_name() != 'cli')
{
	die('Error: index.install.php may only be invoked from the command-line');
}

define('INSTANCE_ROOT', realpath(getcwd()) . '/');

if(file_exists(INSTANCE_ROOT . 'public') || !file_exists(INSTANCE_ROOT . 'index.pho'))
{
	define('PUBLIC_ROOT', INSTANCE_ROOT . 'public/');
}
else
{
	define('PUBLIC_ROOT', INSTANCE_ROOT);
}

if(count($argv) == 1)
{
	$argv[] = 'install';
}

require(dirname(__FILE__) . '/platform.php');

$app->process($request);
