<?php

define('EREGANSU_SKIP_CONFIG', true);
require_once(dirname(__FILE__) . '/../platform.php');

uses('store', 'uuid');

if(count($argv) != 3)
{
	echo "Usage: " . $argv[0] . " STORE-URI UUID\n";
	exit(1);
}

$store = Store::getInstance(array('db' => $argv[1]));

if(!($data = $store->objectForUUID($argv[2])))
{
	echo $argv[2] . ": not found in store\n";
	exit(1);
}

print_r($data);

