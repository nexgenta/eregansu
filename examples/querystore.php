<?php

define('EREGANSU_SKIP_CONFIG', true);
require_once(dirname(__FILE__) . '/../platform.php');

uses('store', 'uuid');

if(count($argv) != 3)
{
	echo "Usage: " . $argv[0] . " STORE-URI PARAM=VALUE ...\n";
	exit(1);
}
array_shift($argv);
$store = Store::getInstance(array('db' => $argv[0]));
array_shift($argv);

$query = array();
foreach($argv as $kv)
{
	$kv = explode('=', $kv, 2);
	$query[trim($kv[0])] = trim($kv[1]);
}
print_r($query);
if(!($rs = $store->query($query)))
{
	echo "Query failed\n";
	exit(1);
}
while(($obj = $rs->next()))
{
	print_r($obj);
}


