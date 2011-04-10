<?php

/* Copyright 2011 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/../../lib/common.php');

define('LOGFILE', 'tests.log');

$php_path = 'php';
$s = getenv('PHP_5');
if(strlen($s))
{
	$php_path = $s;
}   
$tests = array();	
$counts = array('total' => 0, 'pass' => 0, 'fail' => 0, 'xpass' => 0, 'xfail' => 0);
$root = simplexml_load_file('tests.xml');
if(!is_object($root)) exit(1);

$f = fopen(LOGFILE, 'w');
fwrite($f, "Test run beginning at " . strftime('%Y-%m-%dT%H:%M:%SZ') . "\n");
fwrite($f, str_repeat('=', 72) . "\n");
fclose($f);

foreach($root->test as $t)
{
	$attrs = $t->attributes();
	$expect = 'pass';
	$name = strval($t);
	$issue = null;
	if(isset($attrs->expect))
	{
		$expect = strval($attrs->expect);
	}
	if(isset($attrs->issue))
	{
		$issue = strval($attrs->issue);
	}
	$tests[] = array('name' => $name, 'expect' => strtolower($expect), 'issue' => $issue);
}

foreach($tests as $test)
{
	runTest($test);
}

$uxpass = $counts['pass'] - $counts['xpass'];
$uxfail = $counts['fail'] - $counts['xfail'];

$buf = "Total: " . $counts['total'] . ' -- ';
$buf .= $counts['pass'] . ' passed' . ($uxpass ? ' (' . $uxpass . ' unexpected)' : '') . ', ';
$buf .= $counts['fail'] . ' failed' . ($uxfail ? ' (' . $uxfail . ' unexpected)' : '') . ".\n";

$f = fopen(LOGFILE, 'a');
fwrite($f, "Test run completed at " . strftime('%Y-%m-%dT%H:%M:%SZ') . "\n");
fwrite($f, $buf);
fclose($f);

echo $buf;

if(!$uxpass && !$uxfail)
{
	exit(0);
}
exit(1);

function runTest($test)
{
	global $counts, $php_path;

	$f = fopen(LOGFILE, 'a');
	fprintf($f, "%s - Executing test: %s, expected result: %s\n", strftime('%Y-%m-%dT%H:%M:%SZ'), $test['name'], $test['expect']);
	if(strlen($test['issue']))
	{
		fprintf($f, "Issue URL: http://github.com/nexgenta/eregansu/issues/%s\n", $test['issue']);
	}
	$counts['total']++;
	echo sprintf('%20s', $test['name']) . " ... ";
	$status = 256;
	if(file_exists($test['name']))
	{		
		fwrite($f, str_repeat('-', 72) . "\n"); 
		fclose($f);
		$result = system($php_path . ' -f ' . escapeshellarg($test['name']) . ' >>tests.log 2>&1', $status);
		if($result === false)
		{
			$status = 256;
		}
		$f = fopen(LOGFILE, 'a');
		if($status)
		{
			fwrite($f, str_repeat('-', 72) . "\n");
		}
		fprintf($f, "%s - Test %s completed with status %d\n", strftime('%Y-%m-%dT%H:%M:%SZ'), $test['name'], $status);
	}
	else
	{
		fwrite($f, "Test cannot be executed because " . $test['name'] . " does not exist\n");
	}
	fwrite($f, str_repeat('=', 72) . "\n");
	fclose($f);
	if($status > 254)
	{
		echo "*** ERROR *** (failed to execute test)";
		$counts['fail']++;
	}
	else if($status == 0)
	{
		$counts['pass']++;
		if($test['expect'] == 'pass')
		{
			echo "PASS";
			$counts['xpass']++;
		}
		else
		{
			echo "UXPASS";
		}
	}
	else
	{
		$counts['fail']++;
		if($test['expect'] == 'fail')
		{
			echo "XFAIL";
			$counts['xfail']++;
			$status = 0;
			if(strlen($test['issue']))
			{
				echo " -- http://github.com/nexgenta/eregansu/issues/" . $test['issue'];
			}
		}
		else
		{
			echo "FAIL ($status)\n";
		}
	}
	echo "\n";
	return $status;
}
