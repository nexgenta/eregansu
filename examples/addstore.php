<?php

define('EREGANSU_SKIP_CONFIG', true);
require_once(dirname(__FILE__) . '/../platform.php');

uses('module', 'store', 'uuid');

if(count($argv) < 3)
{
	echo "Usage: " . $argv[0] . " STORE-URI JSON-FILE ...\n";
	exit(1);
}

array_shift($argv);

/* Set up the store first - this is a hack */

require_once(PLATFORM_PATH . 'store-module.php');

$module = StoreModule::getInstance(array('db' => $argv[0]));
$module->setup();

$store = Store::getInstance(array('db' => $argv[0]));

array_shift($argv);

foreach($argv as $file)
{
	if(!($obj = json_decode(file_get_contents($file), true)))
	{
		echo "$file could not be read or parsed\n";
		continue;
	}
	if(!isset($obj['uuid']))
	{
		$obj['uuid'] = UUID::generate();
	}
	if($store->setData($obj))
	{
		echo "Added " . $obj['uuid'] . " from $file\n";
	}
	else
	{
		echo "Failed to add " . $obj['uuid'] . " from $file\n";
	}
}
