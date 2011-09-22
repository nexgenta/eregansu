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

class TestUuidIsUuid extends TestHarness
{   
	public static $tests = array(
		'00000000-0000-0000-0000-000000000000' => true,
		'urn:uuid:00000000-0000-0000-c000-000000000046' => true,
		'urn:uuid:0000000000000000c000000000000046' => true,
		'{cfbff0d1-9375-5685-968c-48ce8b15ae17}' => true,
		'{9073926b929f31c2abc9fad77ae3e8eb}' => true,
		'034bd46617ce4dc19332a0ea76457c51' => true,

		'00000000-0000-0000-0000-00000000000000' => false,
		'urn:uuid:00000000-0000-0000-c000-000000000046X' => false,
		'urn:0000000000000000c000000000000046' => false,
		'{cfbff0d1-9375-5685-968c-48ce8b15ae17abc}' => false,
		'{9073926b929f31c2abc9fad77ae3e8eb27}' => false,
		'034bd46617ce4dc19332a0ea76457c5112' => false,
		'[034bd46617ce4dc19332a0ea76457c5112]' => false,
		);

	public function main()
	{
		$r = true;
		foreach(self::$tests as $uuid => $expected)
		{
			$result = UUID::isUUID($uuid);
			$bool = ($result === null ? false : true);
			if($bool !== $expected)
			{
				echo "$uuid: Expected " . intval($expected) . ", UUID::isUUID() returned " . intval($bool) . " [$result]\n";
				$r = false;
			}
		}
		return $r;
	}
}

return 'TestUuidIsUuid';
