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

class TestUuidVersion extends TestHarness
{   
	public static $tests = array(
		'00000000-0000-0000-0000-000000000000' => array('version' => UUID::NONE, 'variant' => UUID::UNKNOWN),
		'00000000-0000-0000-c000-000000000046' => array('version' => UUID::NONE, 'variant' => UUID::MICROSOFT),
		'cfbff0d1-9375-5685-968c-48ce8b15ae17' => array('version' => UUID::HASH_SHA1, 'variant' => UUID::DCE),
		'9073926b-929f-31c2-abc9-fad77ae3e8eb' => array('version' => UUID::HASH_MD5, 'variant' => UUID::DCE),
		'034bd466-17ce-4dc1-9332-a0ea76457c51' => array('version' => UUID::RANDOM, 'variant' => UUID::DCE),
		'a4a9de9a-63c5-11e0-957b-001aa08ec528' => array('version' => UUID::DCE_TIME, 'variant' => UUID::DCE),
		);

	public function main()
	{
		foreach(self::$tests as $uuid => $info)
		{
			$result = UUID::parse($uuid);
			if($info['version'] != $result['version'] || $info['variant'] != $result['variant'])
			{
				echo "$uuid: expected ver=" . $info['version'] . ", var=" . $info['variant'] . " actual ver=" . $result['version'] . ", var=" . $info['variant'] . "\n";
				return false;
			}
		}
		return true;
	}
}

return 'TestUuidVersion';
