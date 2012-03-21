<?php

/* Copyright 2012 Mo McRoberts.
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

uses('db');

if(!defined('MYSQL_SOCKET')) define('MYSQL_SOCKET', realpath(dirname(__FILE__) . '/data/mysql.sock'));

class TestMySQLConnect extends TestHarness
{
	public function main()
	{
		if(!defined('MYSQL_ROOT'))
		{
			return 'skip';
		}
		$db = Database::connect('mysql://root@localhost/test?socket=' . urlencode(MYSQL_SOCKET));
		$result = $db->rows('SHOW DATABASES');
		if(count($result))
		{
			return true;
		}
		print_r($result);
		return false;
	}
}

return 'TestMySQLConnect';
