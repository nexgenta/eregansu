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

if(!defined('TESTSUITE_RUN_TESTS'))
{
	if(!defined('__EREGANSU__'))
	{
		/* Eregansu hasn't previously been included, we're being executed
		 * directly -- run the tests
		 */
		define('TESTSUITE_RUN_TESTS', true);

		/* Don't attempt to load config.php and appconfig.php */
		define('EREGANSU_SKIP_CONFIG', true);
	}
	else
	{
		/* We're being included as part of something else, don't run the
		 * tests
		 */
		define('TESTSUITE_RUN_TESTS', false);
	}
}

require_once(dirname(__FILE__) . '/../platform.php');

class TestHarness
{
	public function main()
	{
		echo "This test has not yet been implemented.\n";
		return false;
	}
}

if(TESTSUITE_RUN_TESTS)
{
	if(!isset($argv) || count($argv) != 2)
	{
		echo "Usage: php -f " . __FILE__ . " PATH-TO-TEST.php\n";
		exit(1);
	}
	
	$className = require($argv[1]);
	
	if(strlen($className))
	{
		$inst = new $className();
		if(($r = $inst->main()) !== true)
		{
			exit(empty($r) ? 1 : $r);
		}
	}
	else
	{
		echo $argv[1] . ": no class name returned to the testsuite harness\n";
		exit(1);
	}
}

