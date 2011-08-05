<?php

/* Generate API documentation from PHP sources
 *
 * Copyright 2011 Mo McRoberts
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

class Tokenizer implements IteratorAggregate
{
	protected $tokens;

	public static function tokenizeFile($path, $className = null)
	{
		$buf = file_get_contents($path);
		if($buf === null || $buf === false)
		{
			return null;
		}
		return self::tokenize($buf, $className);
	}
	
	public static function tokenize($string, $className = null)
	{
		$tokens = token_get_all($string);
		if(!is_array($tokens))
		{
			return null;
		}
		if($className === null)
		{
			$className = 'Tokenizer';
		}
		return new $className($tokens);
	}

	protected function __construct($tokens)
	{
		$this->tokens = $tokens;
	}

	/**
	 * Implements IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->tokens);
	}
}
