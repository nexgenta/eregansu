<?php

if(!defined('MYSQL_ROOT')) exit(126);

$datadir = realpath(dirname(__FILE__) . '/data');

if(!file_exists($datadir . '/mysql.pid'))
{
	echo "MySQL PID file does not exist\n";
	exit(0);
}
$pid = intval(trim(file_get_contents($datadir . '/mysql.pid')));
echo "Killing PID " . $pid . "\n";
if(function_exists('posix_kill'))
{
	if(posix_kill($pid, 15) === false)
	{
		exit(1);
	}
}
else
{
	$status = 255;
	system('kill -TERM ' . escapeshellarg($pid), $status);
	if($status != 0)
	{
		exit($status);
	}
}
unlink($datadir . '/mysql.pid');

exit(0);
