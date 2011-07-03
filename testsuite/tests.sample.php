#! /usr/bin/env php -f
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

/* Copy this file into your tests directory and arrange for it to be
 * executed in order to run your testsuite. Test details are in
 * tests.sample.xml
 */

require_once(dirname(__FILE__) . '/eregansu/lib/common.php');
require_once(PLATFORM_ROOT . 'testsuite/testsuite.php');

$suite = TestSuite::suiteFromXML('tests.sample.xml', 'tests.log');
$suite->run();
if($suite->uxpass || $suite->uxfail)
{
	exit(1);
}
exit(0);
