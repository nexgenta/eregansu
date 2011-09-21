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

uses('uuid');

class TestUuidFormatted extends TestHarness
{   
	public static $tests = array(
		'00000000-0000-0000-0000-000000000000' =>          '00000000-0000-0000-0000-000000000000',
		'urn:uuid:00000000-0000-0000-c000-000000000046' => '00000000-0000-0000-c000-000000000046',
		'urn:uuid:0000000000000000c000000000000046' =>     '00000000-0000-0000-c000-000000000046',
		'{cfbff0d1-9375-5685-968c-48ce8b15ae17}' =>        'cfbff0d1-9375-5685-968c-48ce8b15ae17',
		'{9073926b929f31c2abc9fad77ae3e8eb}' =>            '9073926b-929f-31c2-abc9-fad77ae3e8eb',
		'034bd46617ce4dc19332a0ea76457c51' =>              '034bd466-17ce-4dc1-9332-a0ea76457c51',
		'00000000-0000-0000-0000-00000000000000' => null,
		'urn:uuid:00000000-0000-0000-c000-000000000046X' => null,
		'urn:0000000000000000c000000000000046' => null,
		'{cfbff0d1-9375-5685-968c-48ce8b15ae17abc}' => null,
		'{9073926b929f31c2abc9fad77ae3e8eb27}' => null,
		'034bd46617ce4dc19332a0ea76457c5112' => null,
		'[034bd46617ce4dc19332a0ea76457c5112]' => null,
		);

	public function main()
	{
		$r = true;
		foreach(self::$tests as $uuid => $expected)
		{
			$result = UUID::formatted($uuid);
			if($result !== $expected)
			{
				echo "$uuid: Expected " . $expected . ", UUID::formatted() returned " . $result . "\n";
				$r = false;
			}
		}
		return $r;
	}
}

return 'TestUuidFormatted';
