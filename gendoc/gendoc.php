#!/usr/bin/env php
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

/**
 * @internal
 */

define('EREGANSU_SKIP_CONFIG', true);
require_once(dirname(__FILE__) . '/../platform.php');

require_once(dirname(__FILE__) . '/parse.php');
require_once(dirname(__FILE__) . '/markdown.php');

class GenDoc extends CommandLine
{
	protected $extensions = array('php');

	protected $minArgs = 1;
	protected $options = array(
		'output' => array(
			'value' => 'o', 
			'has_arg' => true,
			'description' => 'Place generated files in the specified path',
			'flag' => '..',
			),
		);

	public function main($args)
	{
		if(!file_exists($this->options['output']['flag']))
		{
			mkdir($this->options['output']['flag'], 0777, true);
		}
		$c = 0;
		foreach($args as $arg)
		{
			if(!file_exists($arg))
			{
				$this->request->err($arg . ': no such file or directory');
			}
			else if(is_dir($arg))
			{
				$c++;
				$this->processDirectory($arg);
			}
			else
			{
				$c++;
				$this->processFile($arg);
			}
		}
		if($c === count($args))
		{
			return 0;
		}
		return ($c ? 2 : 1);
	}

	protected function processDirectory($path, $relPath = null, $global = null)
	{
		$modules = array();
		if($relPath === null && file_exists($path . '/.gendocrc'))
		{
			$global = $this->parseRC($path . '/.gendocrc');
		}
		$d = opendir($path);
		while(($de = readdir($d)) !== false)
		{
			$rp = $relPath . $de;
			if($de[0] == '.' || !file_exists($path  . '/' . $de))
			{
				continue;
			}
			if(isset($global['ignore']) && in_array($rp, $global['ignore']))
			{
				continue;
			}
			if(is_dir($path . '/' . $de))
			{
				$list = $this->processDirectory($path . '/' . $de, $rp . '/', $global);
				$modules = array_merge($modules, $list);
			}
			else
			{
				$info = pathinfo($path . '/' . $de);
				if(in_array(@$info['extension'], $this->extensions))
				{
					$modules[] = $this->processFile($path . '/' . $de, $rp, $global);
				}
			}
		}
		closedir($d);
		if($relPath === null)
		{
			$this->processPackage($modules);
		}
		return $modules;
	}

	protected function processPackage($modules)
	{
		$packages = array();
		foreach($modules as $mod)
		{
			foreach($mod->classes as $className => $info)
			{
				if(isset($info['doc']['package']))
				{
					$pkgx = explode(' ', str_replace('  ', ' ', $info['doc']['package']), 2);
				}
				else
				{
					$pkgx = array('default');
				}
				if(!isset($packages[$pkgx[0]]))
				{
					$packages[$pkgx[0]] = array(
						'title' => null,
						'interfaces' => array(),
						'classes' => array(),
						'constants' => array(),
						'functions' => array(),
						'tasks' => array(),
						);
				}
				if(isset($pkgx[1]) && strlen($pkgx[1]) &&
				   !strlen($packages[$pkgx[0]]['title']))
				{
					$packages[$pkgx[0]]['title'] = $pkgx[1];
				}
				if($info['type'] == 'class')
				{
					$packages[$pkgx[0]]['classes'][] = $className;
				}
				else if($info['type'] == 'interface')
				{
					$packages[$pkgx[0]]['interfaces'][] = $className;
				}
				if(isset($info['methods']) && count($info['methods']))
				{
					foreach($info['methods'] as $method)
					{
						if(strlen(@$method['doc']['task']))
						{
							$packages[$pkgx[0]]['tasks'][$method['doc']['task']][$info['ident'] . '::' . $method['ident']] = $method['doc']['brief'];
						}
					}
				}
			}
		}
		foreach($packages as $name => $package)
		{
			$md = new GenMarkdown();
			$md->generatePackage($name, $package, $this->options['output']['flag'] . '/markdown');
		}
	}

	protected function parseRC($path)
	{
		$options = array('ignore' => array());
		$lines = explode("\n", file_get_contents($path));
		foreach($lines as $line)
		{
			$line = str_replace("\t", ' ', trim($line));
			if(!strlen($line) || $line[0] == '#')
			{
				continue;
			}
			$line = explode(' ', $line, 2);
			if(!isset($line[1]))
			{
				continue;
			}
			$line[1] = trim($line[1]);
			if(!strlen($line[1]))
			{
				continue;
			}
			if(!strcmp($line[0], 'ignore'))
			{
				$options['ignore'][] = $line[1];
			}
			else
			{
				$options[$line[0]] = $line[1];
			}
		}
		return $options;
	}

	protected function processFile($filename, $relPath = null, $global = null)
	{
		$module = Parser::tokenizeFile($filename);
		$module->cascadeAttributes($global);
		if(!isset($module->module['source']) && $relPath !== null && isset($module->module['sourcebase']))
		{
			$base = $module->module['sourcebase'];
			if(substr($base, -1) != '/')
			{
				$base .= '/';
			}
			$module->module['source'] = $base . $relPath;
		}
		$md = new GenMarkdown();
		$md->generateFrom($module, $this->options['output']['flag'] . '/markdown');
		return $module;
	}
}

$genDoc = new GenDoc();
$genDoc->process($request);

