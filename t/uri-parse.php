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

uses('uri');

class TestUriParsing extends TestHarness
{
	protected $tests = array(
		'about:blank' => array('scheme' => 'about', 'path' => 'blank'),
		'http://www.example.com/' => array('scheme' => 'http', 'host' => 'www.example.com', 'path' => '/'),
		'http://example.com/?' => array('scheme' => 'http', 'host' => 'example.com', 'path' => '/'),
		'http://example.com/?test' => array('scheme' => 'http', 'host' => 'example.com', 'path' => '/', 'query' => 'test'),
		'http://example.com/?test#' => array('scheme' => 'http', 'host' => 'example.com', 'path' => '/', 'query' => 'test'),
		'http://example.com/carrot#question%3f' => array('scheme' => 'http', 'host' => 'example.com', 'path' => '/carrot', 'fragment' => 'question%3f'),
		'https://www.example.com:4443?' => array('scheme' => 'https', 'host' => 'www.example.com', 'port' => 4443),
	);
	
	public function main()
	{
		static $keys = array(
			'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment',
		);
		$success = true;
		foreach($this->tests as $testUri => $expect)
		{
			$uri = new URI($testUri, isset($expect['base']) ? $expect['base'] : null);
			foreach($keys as $key)
			{
				$v = $uri->{$key};
				$exv = isset($expect[$key]) ? $expect[$key] : null;
				if($v !== $exv)
				{
					$exv = ($exv === null ? '(null)' : $exv);
					$v = ($v === null ? '(null)' : $v);
					echo "$testUri: Expected $key to be '$exv', is actually '$v'\n";
					$success = false;
				}
			}
		}
		return $success;
	}
}

return 'TestUriParsing';
