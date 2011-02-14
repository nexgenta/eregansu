<?php

/* Copyright 2010-2011 Mo McRoberts.
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

abstract class ModuleInstaller
{
	protected $installer;
	public $name;
	public $path;
	public $moduleOrder = 1000;
	
	public function __construct($installer, $name, $path)
	{
		$this->installer = $installer;
		if(!strlen($this->name))
		{
			$this->name = $name;
		}
		$this->path = $path;
	}
	
	protected function writePlaceholderDBIri($file, $constant = null, $dbname = null, $dbtype = 'mysql', $options = null)
	{
		if(0 == strlen($constant))
		{
			$constant = strtoupper($this->name . '_IRI');
		}
		if(0 == strlen($dbname))
		{
			$dbname = $this->name;
		}
		if(0 != strlen($options))
		{
			$options = '?' . $options;
		}
		fwrite($file, '/* define(\'' . $constant . '\', \'' . $dbtype . '://username:password@localhost/' . $dbname . $options . '\'); */' . "\n");
	}
	
	public function writeAppConfig($file)
	{
	}
	
	public function writeInstanceConfig($file)
	{
	}
	
	public function createLinks()
	{
		$this->linkTemplates();
	}
	
	protected function linkTemplates($subdir = 'templates', $target = null)
	{
		if(!strlen($target))
		{
			$target = $this->name;
		}
		if(substr($this->installer->relModulesPath, 0, 1) == '/')
		{
			$rpath = $this->installer->relModulesPath;
		}
		else
		{
			$rpath = '../../' . $this->installer->relModulesPath;
		}
		if(substr($rpath, -1) != '/') $rpath .= '/';
		$rpath .= $this->name . '/' . $subdir;
		$path = PUBLIC_ROOT . (defined('TEMPLATES_PATH') ? TEMPLATES_PATH : 'templates') . '/';
		if(file_exists($path) && file_exists(MODULES_ROOT . $this->name . '/' . $subdir))
		{
			if(file_exists($path . $target))
			{
				echo "  > Leaving existing file at " . $path . $target . " in place\n";
			}
			else
			{
				echo "  > Linking $rpath to $target in $path\n";
				@unlink($path . $target);
				symlink($rpath, $path . $target);
			}
		}
	}
}