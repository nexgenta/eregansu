<?php

/* Base class for formatters
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

abstract class GenFormatter
{
	protected $currentScope = null;
	protected $currentClass = null;

	public function __construct()
	{
	}

	protected function format($str)
	{
		$this->cur = $str;
		$str = str_replace("\n\n", '\newpara{.}', $str);
		$str = str_replace("\n", ' ', $str);  
		$str = preg_replace_callback('!\\\([a-z]+)\{([^\}]+)\}!mis', array($this, '_formatReplacement'), $str);
		$str = preg_replace_callback('!\\\([a-z]+)\{|(.*)|\}!misU', array($this, '_formatReplacement'), $str);
		return $str;
	}

	public /*callback*/ function _formatReplacement($matches)
	{
		$type = $matches[1];
		$thing = explode('|', $matches[2], 2);
		if(isset($thing[1]))
		{
			$label = $thing[1];
		}
		else
		{
			$label = null;
		}
		$thing = $thing[0];
		switch($type)
		{
		case 'newpara':
			return $this->newPara();
		case 'P':
		case 'prop':
		case 'property':
			return $this->property($thing, $label);
		case 'C':
		case 'class':
			return $this->classname($thing, $label);
		case 'v':
		case 'var':
			return $this->variable($thing, $label);
		case 'm':
		case 'method':
			return $this->method($thing, $label);
		case 'function':
			return $this->func($thing, $label);
		case 'k':
		case 'keyword':
		case 'c':
		case 'const':
		case 'p':
		case 'param':
			return $this->keyword($thing);
		case 'code':
		case 'x':
			return $this->code($thing);
		case 'l':
		case 'link':
			return $this->hyperlink($thing, $label);
		default:
			return $thing;
		}
	}
}
