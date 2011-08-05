<?php

/* Generate HTML
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

require_once(dirname(__FILE__) . '/formatter.php');

class GenHTML extends GenFormatter
{
	protected function newPara()
	{
		return '</p><p>';
	}
	
	protected function keyword($thing)
	{
		return '<code>' . _e($thing) . '</code>';
	}

	protected function code($thing)
	{
		return '<code>' . _e($thing) . '</code>';
	}

	protected function property($thing, $label = null)
	{
		if(strpos($thing, '::') === false)
		{
			$thing = $this->currentClass . '::' . $thing;
		}
		if(!strcmp($thing, $this->currentScope))
		{
			if(strlen($label))
			{
				return $label;
			}
			return '<code>' . _e($thing) . '()</code>';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		else
		{
			return '<code>' . $this->link($thing, $label) . '</code>';
		}
	}

	protected function classname($thing, $label = null)
	{
		if(!strcmp($thing, $this->currentScope))
		{
			if(strlen($label))
			{
				return _e($label);
			}
			return '<code>' . _e($thing) . '</code>';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '<code>' . $this->link($thing, $label) . '</code>';
	}

	protected function variable($thing, $label = null)
	{
		if(strlen($label))
		{
			return _e($label);
		}
		return '<code>' . $thing . '</code>';
	}

	protected function func($thing, $label = null)
	{
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '<code>' . $this->link($thing, $label) . '()</code>';
	}
	
	protected function method($thing, $label = null)
	{
		if(strpos($thing, '::') === false)
		{
			$thing = $this->currentClass . '::' . $thing;
		}
		if(!strcmp($thing, $this->currentScope))
		{
			if(strlen($label))
			{
				return _e($label);
			}
			return '<code>' . _e($thing) . '()</code>';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '<code>' . $this->link($thing, $label) . '()</code>';
	}

	protected function link($text, $label = null)
	{
		if(!strlen($label))
		{
			$label = $text;
		}
		return '<a href="' . _e($text) . '">' . _e($label) . '</a>';
	}

	protected function hyperlink($text, $label = null)
	{
		return $this->link($text, $label);
	}
}
