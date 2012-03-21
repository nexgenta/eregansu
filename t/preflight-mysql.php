<?php

if(!defined('MYSQL_ROOT')) exit(126);

$datadir = realpath(dirname(__FILE__) . '/data');

echo "Populating data directory\n";
system('rm -rf ' . escapeshellarg($datadir . '/mysql'));
mkdir($datadir . '/mysql', 0777, true);
system(MYSQL_ROOT . '/scripts/mysql_install_db --basedir=' . escapeshellarg(MYSQL_ROOT) . ' --datadir=' . escapeshellarg($datadir . '/mysql') . ' --force');

$args = array(
	'--no-defaults',
	'--datadir=' . $datadir . '/mysql',
	'--innodb-data-home-dir=' . $datadir . '/mysql',
	'--socket=' . $datadir . '/mysql.sock',
	'--pid-file=' . $datadir . '/mysql.pid',
	'--port=13306',
	'--console',
	'-C', 'UTF8',
	'--default-time-zone=+00:00',
	'--default-storage-engine=InnoDB',
);

foreach($args as $k => $arg)
{
	$args[$k] = escapeshellarg($arg);
}
$f = fopen($datadir . '/mysql/startup.sh', 'w');
fwrite($f, "#! /bin/sh\n\n");
fwrite($f, 'nohup -- ' . MYSQL_ROOT . '/bin/mysqld ' . implode(' ', $args)  . ' >mysql.log 2>mysql.log &' . "\n");
fclose($f);
chmod($datadir . '/mysql/startup.sh', 0755);
echo "Launching mysqld\n";
system('cd ' . escapeshellarg($datadir . '/mysql') . ' && ./startup.sh');
echo "Done\n";
exit(0);
