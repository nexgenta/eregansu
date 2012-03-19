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

uses('uri');

class TestUriContract extends TestHarness
{   
	public static $tests = array(
		/* Pre-defined namespaces */
		'http://purl.org/dc/terms/title' => array('expect' => 'dct:title'),
		'http://www.w3.org/2001/XMLSchema#dateTime' => array('expect' => 'xsd:dateTime'),
		/* Pre-defined namespace without a sensible terminating character */
		'http://www.w3.org/XML/1998/namespace lang' => array('expect' => 'xml:lang'),
		/* Undefined namespace */
		'http://example.org/#foo' => array('expect' => null),
		/* Undefined namespace, forcing contraction (will return, e.g., ns999:foo) */
		'http://example.org/#foo' => array('regexp' => '/^ns[0-9]+:foo$/', 'alwaysContract' => true),
		/* Register a namespace and then contract using it */
		'http://example.com/#foo' => array('expect' => 'ex:foo', 'register' => array('uri' => 'http://example.com/#', 'prefix' => 'ex', 'overwrite' => 'true')),
		);

	public function main()
	{
		$r = true;
		foreach(self::$tests as $uri => $info)
		{
			if(isset($info['register']))
			{
				URI::registerPrefix($info['register']['prefix'], $info['register']['uri'], !empty($info['register']['overwrite']));
			}
			$result = URI::contractUri($uri, !empty($info['alwaysContract']));
			if(isset($info['expect']))
			{
				if($result !== $info['expect'])
				{
					echo "$uri: Expected " . $info['expect'] . ", URI::contractUri() returned " . $result . "\n";
					$r = false;
				}
			}
			else if(isset($info['regexp']))
			{
				if(!preg_match($info['regexp'], $result))
				{
					echo "$uri: Expected match for " . $info['regexp'] . ", URI::contractUri() returned " . $result . "\n";
					$r = false;
				}
			}
			else
			{
				echo "$uri: No success criteria defined\n";
				$r = false;
			}
		}
		return $r;
	}
}

return 'TestUriContract';
