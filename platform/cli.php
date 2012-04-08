<?php

/* Eregansu: Additional command-line support
 *
 * Copyright 2009-2011 Mo McRoberts.
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
 * @year 2009-2011
 * @since Available in Eregansu 1.0 and later. 
 */

/**
 * Implements the default 'help' command-line route
 */
class CliHelp extends CommandLine
{
	public function main($args)
	{
		if(!isset($this->request->data['_routes']))
		{
			echo "No help available\n";
			return;
		}
		echo "Available commands:\n";
		$routes = $this->request->data['_routes'];
		ksort($routes);
		foreach($routes as $cmd => $info)
		{
			if(substr($cmd, 0, 1) == '_') continue;
			if(!isset($info['description'])) continue;
			echo sprintf("  %-25s  %s\n", $cmd, $info['description']);
		}
	}
}

/* For each registered module, perform any necessary database schema updates */
class CliSetup extends CommandLine
{
	protected $modules = array();
	
	public function main($args)
	{
		global $SETUP_MODULES;
		
		uses('module');

		if(!isset($SETUP_MODULES) || !is_array($SETUP_MODULES) || !count($SETUP_MODULES))
		{
			echo "*** No modules are configured, nothing to do.\n";
			exit(0);
		}
		foreach($SETUP_MODULES as $mod)
		{
			$this->load($mod);
		}
		$this->load(array('name' => 'id', 'file' => PLATFORM_PATH . 'id-module.php', 'class' => 'IdentityModule'), null);
		$this->load(array('name' => 'store', 'file' => PLATFORM_PATH . 'store-module.php', 'class' => 'StoreModule'), null);		
		foreach($this->modules as $k => $mod)
		{
			$this->processSetup($k);
		}
		echo ">>> Module setup completed successfully.\n";
	}
	
	protected function processSetup($key)
	{
		$mod = $this->modules[$key];
		if($mod['processed'])
		{
			return true;
		}
		echo ">>> Updating schema of " . $mod['id'] . "\n";
		$this->modules[$key]['processed'] = true;
		if(!$mod['module']->setup())
		{
			echo "*** Schema update of " . $mod['id'] . " to " . $mod['module']->latestVersion . " failed\n";
			exit(1);
		}
	}

	protected function load($mod, $iri = null)
	{
		global $MODULE_ROOT;
		
		$root = $MODULE_ROOT;
		if(!is_array($mod))
		{
			$mod = array('name' => $mod);
		}
		if(isset($mod['name']))
		{
			if(!isset($mod['file']))
			{
				if(file_exists(MODULES_ROOT . $mod['name'] . '/module.php'))
				{
					$mod['file'] = 'module.php';
				}
				else
				{
					$mod['file'] = 'schema.php';
				}
			}
			if(!isset($mod['class']))
			{
				$mod['class'] = $mod['name'] . 'Module';
			}
		}
		if(isset($mod['name']))
		{
			$MODULE_ROOT = MODULES_ROOT . $mod['name'] . '/';
		}
		if(isset($mod['file']))
		{
			if(substr($mod['file'], 0, 1) == '/')
			{
				require_once($mod['file']);
			}
			else
			{
				require_once($MODULE_ROOT . $mod['file']);
			}
		}
		$cl = $mod['class'];
		$module = call_user_func(array($cl, 'getInstance'), array('cli' => $this, 'db' => $iri));
		if(!$module)
		{
			echo "*** Failed to retrieve module instance ($cl)\n";
			exit(1);
		}
		$key = $module->moduleId . '-' . $module->dbIri;
		if(defined('EREGANSU_DEBUG'))
		{
			echo "--> Loaded " . $mod['name'] . " from class " . get_class($module) . " with key " . $key . "\n";
		}
		if(!isset($this->modules[$key]))
		{
			$mod['key'] = $key;
			$mod['module'] = $module;
			$mod['id'] = $module->moduleId;
			$mod['processed'] = false;
			$mod['iri'] = $module->dbIri;
			$this->modules[$key] = $mod;
		}
		$MODULE_ROOT = $root;
		return $this->modules[$key];
	}
	
	public function depend($id, $iri, $info = null)
	{
		$key = $id . '-' . $iri;
		if(isset($this->modules[$key]))
		{
			$this->processSetup($key);
			return;
		}
		foreach($this->modules as $mod)
		{
			if(!strcmp($mod['id'], $id))
			{
				$mod = $this->load($mod, $iri);
				$this->processSetup($mod['key']);
				return;
			}
		}
		if($info === null)
		{
			trigger_error('Cannot load dependency ' . $id . ' because it does not exist', E_USER_ERROR);
			exit(1);
		}
		$mod = $this->load($info);
		$this->processSetup($mod['key']);
	}
}
