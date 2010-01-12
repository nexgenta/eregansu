<?php

require(dirname(__FILE__) . '/platform.php');

if(count($argv) == 1)
{
	$argv[] = 'setup';
}

$app->process($request);
