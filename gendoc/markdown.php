<?php

/* Generate Github-Flavored Markdown (GFM)
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
require_once(dirname(__FILE__) . '/html.php');

class GenMarkdown extends GenFormatter
{
	protected $html;

	public function __construct()
	{
		parent::__construct();
		$this->html = new GenHTML();
	}

	public function generateFrom($module, $path)
	{
		if(!file_exists($path))
		{
			mkdir($path, 0777, true);
		}
		foreach($module->classes as $className => $info)
		{
			$this->generateClass($module, $path, $className, $info);

		}
	}

	public function generatePackage($shortName, $info, $path)
	{
		if(!file_exists($path))
		{
			mkdir($path, 0777, true);
		}
		if(!isset($info['title'])) $info['title'] = $shortName;
		$f = fopen($path . '/' . $shortName . '.md', 'w');
		fwrite($f, '# ' . $info['title'] . "\n\n");
		if(count($info['tasks']))
		{
			fwrite($f, "## Tasks\n\n");
			foreach($info['tasks'] as $taskName => $refs)
			{
				$short = $this->generateTask($taskName, $refs, $path);
				fwrite($f, "* [[" . $taskName . "|" . $short . "]]\n");
			}
			fwrite($f, "\n");
		}
		if(count($info['interfaces']))
		{
			fwrite($f, "## Interfaces\n\n");
			foreach($info['interfaces'] as $name)
			{
				fwrite($f, "* `[[" . $name . "]]`\n");
			}
			fwrite($f, "\n");
		}
		if(count($info['classes']))
		{
			fwrite($f, "## Classes\n\n");
			foreach($info['classes'] as $name)
			{
				fwrite($f, "* `[[" . $name . "]]`\n");
			}
			fwrite($f, "\n");
		}
		fclose($f);
	}

	protected function generateTask($title, $refs, $path)
	{
		$shortName = preg_replace('![^a-z0-9.-:]!i', '-', $title);
		$f = fopen($path . '/' . $shortName . '.md', 'w');
		fwrite($f, '# ' . $title . "\n\n");
		foreach($refs as $ref => $brief)
		{
			$suf = '';
			if(strpos($ref, '$') === false)
			{
				$suf = '()';
			}
			if(strlen($brief))
			{
				$brief = ': ' . $brief;
			}
			fwrite($f, "* `[[" . $ref . "]]" . $suf . "`" . $brief . "\n");
		}
		fclose($f);
		return $shortName;
	}

	protected function generateClass($module, $path, $className, $info)
	{
		$this->currentScope = $this->currentClass = $className;
		if(!empty($info['doc']['internal'])) return;;
		$f = fopen($path . '/' . $className . '.md', 'w');
		if(strlen(@$info['doc']['brief']))
		{
			fwrite($f, $this->format($info['doc']['brief']) . "\n\n");
		}
		$line = '`' . $className . '` is ';
		$kind = '';
		if($info['modifiers'] !== null && in_array('abstract', $info['modifiers']))
		{
			$kind = 'abstract';
		}
		if(count($info['extends']))
		{
			$parentage = ' derived from [[' . $info['extends'][0] . ']]';
		}
		else
		{
			$parentage = '';
			$kind .= ' base';
		}
		$kind = trim($kind . ' ' . $info['type']);
		$line .= ($kind[0] == 'a' || $kind[0] == 'i' ? 'an' : 'a') . ' ' . $kind . $parentage;
		fwrite($f, $line . ".\n\n");
		if(isset($info['doc']['source']))
		{
			fwrite($f, '[View source](' . $module->module['source'] . ')' . "\n\n");
		}
		if(isset($info['doc']['include']) || isset($info['doc']['synopsis']))
		{
			fwrite($f, "## Synopsis\n\n```php\n");
			$p = '';
			if(isset($info['doc']['include']))
			{
				fwrite($f, $info['doc']['include'] . "\n");
				$p = "\n";
			}
			if(isset($info['doc']['synopsis']))
			{
				fwrite($f, $p . $info['doc']['synopsis'] . "\n");
			}
			fwrite($f, "```\n\n");
		}
		if(strlen(@$info['doc']['desc']))
		{
			fwrite($f, "## Description\n\n");
			fwrite($f, $this->format($info['doc']['desc']) . "\n\n");
		}
		if(strlen(@$info['doc']['note']))
		{
			fwrite($f, "## Note\n\n");
			fwrite($f, $this->format($info['doc']['note']) . "\n\n");
		}
		$pubmethods = array();
		$pubsmethods = array();
		foreach($info['methods'] as $k => $method)
		{
			if(!empty($method['doc']['internal']) ||
			   in_array('private', $method['modifiers']) ||
			   in_array('protected', $method['modifiers']))
			{
				continue;
			}
			if(in_array('static', $method['modifiers']))
			{
				$pubsmethods[$k] = $method;
			}
			else
			{
				$pubmethods[$k] = $method;
			}
		}
		if(count($pubsmethods))
		{
			fwrite($f, "## Public Static Methods\n\n");
			foreach($pubsmethods as $name => $method)
			{
				$this->generateMethod($module, $path, $className, $info, $name, $method); 
				$d = '';
				if(strlen(@$method['doc']['brief']))
				{
					$d .= ': ' . $this->format($method['doc']['brief']);
				}
				fwrite($f, "* `[[" . $className . '::' . $name . "]]()`" . $d . "\n");
			}
			fwrite($f, "\n");
		}
		if(count($pubmethods))
		{
			fwrite($f, "## Public Methods\n\n");
			foreach($pubmethods as $name => $method)
			{
				$this->generateMethod($module, $path, $className, $info, $name, $method);
				$d = '';
				if(strlen(@$method['doc']['brief']))
				{
					$d .= ': ' . $this->format($method['doc']['brief']);
				}
				fwrite($f, "* `[[" . $className . '::' . $name . "]]()`" . $d . "\n");
			}
			fwrite($f, "\n");
		}
		fclose($f);
	}

	protected function generateMethod($module, $path, $className, $classInfo, $methodName, $methodInfo)
	{
		$prevScope = $this->currentScope;
		$this->currentScope .= '::' . $methodName;
		if(!empty($methodInfo['doc']['internal'])) return;
		if(strlen($className))
		{
			$f = fopen($path . '/' . $className . '::' . $methodName . '.md', 'w');
		}
		else
		{
			$f = fopen($path . '/' . $methodName . '.md', 'w');
		}
		if(isset($methodInfo['doc']['brief']) && strlen($methodInfo['doc']['brief']))
		{
			fwrite($f, $this->format($methodInfo['doc']['brief']) . "\n\n");
		}
		fwrite($f, "## Synopsis\n\n");
		$line = array();
		if(is_array($methodInfo['modifiers']))
		{
			$line[] = implode(' ', $methodInfo['modifiers']) . ' ';
		}
		$line[] = 'function ';
		if(strlen(@$methodInfo['doc']['type']))
		{
			$line[] = '<i>' . $methodInfo['doc']['type'] . '</i> ';
		}
		if(strlen($className))
		{
			$line[] = '<b>[[' . $className . ']]::';
		}
		$line[] = $methodName . '</b>';
		$line[] = '(';
		if(isset($methodInfo['params']))
		{
			$plist = array_values($methodInfo['params']);
			$max = count($plist) - 1;
			foreach($plist as $i => $param)
			{
				if(isset($methodInfo['doc']['params'][$param['ident']]))
				{
					$param = array_merge($param, $methodInfo['doc']['params'][$param['ident']]);
				}
				$plist[$i] = $param;
				if(isset($param['type']))
				{
					$line[] = '<i>' . $param['type'] . '</i> ';
				}
				if(isset($param['direction']))
				{
					$line[] = '<i>[' . $param['direction'] . ']</i> ';
				}
				$line[] = '<b>' . $param['ident'] . '</b>';
				if(isset($param['value']))
				{
					$line[] = ' = ' . $param['value'][1];
				}
				if($i < $max)
				{
					$line[] = ', ';
				}
			}
			if(isset($methodInfo['doc']['varargs']))
			{
				if(count($plist))
				{
					$line[] = ', ';
				}
				$line[] = '...';
			}
		}
		else
		{
			$plist = array();
		}
		$line[] = ')';
		fwrite($f, "<code>" . implode('', $line) . "</code>\n\n");
		if(strlen(@$methodInfo['doc']['desc']))
		{
			fwrite($f, "## Description\n\n");
			fwrite($f, $this->format($methodInfo['doc']['desc']) . "\n\n");
		}
		if(strlen(@$methodInfo['doc']['note']))
		{
			fwrite($f, "## Note\n\n");
			fwrite($f, $this->format($methodInfo['doc']['note']) . "\n\n");
		}
		if(count($plist))
		{
			$this->html->currentClass = $this->currentClass;
			$this->html->currentScope = $this->currentScope;
			fwrite($f, "## Parameters\n\n");
			fwrite($f, '<table>' . "\n");
			fwrite($f, '  <thead>' . "\n");
			fwrite($f, '    <tr>' . "\n");
			fwrite($f, '      <th>Name</th>' . "\n");
			fwrite($f, '      <th>Direction</th>' . "\n");
			fwrite($f, '      <th>Type</th>' . "\n");
			fwrite($f, '      <th>Description</th>' . "\n");
			fwrite($f, '    </tr>' . "\n");			
			fwrite($f, '  </thead>' . "\n");
			fwrite($f, '  <tbody>' . "\n");
			foreach($plist as $param)
			{
				fwrite($f, '    <tr>' . "\n");
				fwrite($f, '      <td><code>' . $param['ident'] . '</code>' . "\n");
				fwrite($f, '      <td><i>' . @$param['direction'] . '</i></td>' . "\n");
				fwrite($f, '      <td>' . @$param['type'] . '</td>' . "\n");
				fwrite($f, '      <td>' . "\n");
				fwrite($f, $this->html->format(@$param['desc']) . "\n");
				fwrite($f, '      </td>' . "\n");
				fwrite($f, '    </tr>' . "\n");
			}
			fwrite($f, '  </tbody>' . "\n");
			fwrite($f, '</table>' . "\n\n");
		}
		if(strlen(@$methodInfo['doc']['return']))
		{
			fwrite($f, "## Return Value\n\n");
			fwrite($f, $this->format($methodInfo['doc']['return']) . "\n\n");
		}
		fclose($f);
		$this->currentScope = $prevScope;
	}
	
	protected function newPara()
	{
		return "\n\n";
	}
	
	protected function keyword($thing)
	{
		return '`' . $thing . '`';
	}

	protected function code($thing)
	{
		return '`' . $thing . '`';
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
			return '`' . $thing . '()`';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		else
		{
			return '`' . $this->link($thing, $label) . '`';
		}
	}

	protected function classname($thing, $label = null)
	{
		if(!strcmp($thing, $this->currentScope))
		{
			if(strlen($label))
			{
				return $label;
			}
			return '`' . $thing . '`';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '`' . $this->link($thing, $label) . '`';
	}

	protected function variable($thing, $label = null)
	{
		if(strlen($label))
		{
			return $label;
		}
		return '`' . $thing . '`';
	}

	protected function func($thing, $label = null)
	{
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '`' . $this->link($thing, $label) . '()`';
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
				return $label;
			}
			return '`' . $thing . '()`';
		}
		if(strlen($label))
		{
			return $this->link($thing, $label);
		}
		return '`' . $this->link($thing, $label) . '()`';
	}

	protected function link($text, $label = null)
	{
		if(isset($label))
		{
			return '[[' . $label . '|' . $text . ']]';
		}
		return '[[' . $text . ']]';
	}

	protected function hyperlink($text, $label = null)
	{
		if(strlen($label))
		{
			return '[' . $label . '](' . $text . ')';
		}
		return $text;
	}
}

