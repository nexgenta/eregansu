<?php

/* Eregansu: Additional command-line support
 *
 * Copyright 2009, 2010 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * @framework Eregansu
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
	public function main($args)
	{
		global $SETUP_MODULES, $MODULE_ROOT;
		
		if(!isset($SETUP_MODULES) || !is_array($SETUP_MODULES) || !count($SETUP_MODULES))
		{
			echo "setup: No modules are configured, nothing to do.\n";
			exit(0);
		}
		$root = $MODULE_ROOT;
		foreach($SETUP_MODULES as $mod)
		{
			if(!is_array($mod))
			{
				$mod = array('name' => $mod, 'file' => 'module.php', 'class' => $mod . 'Module');
			}
			if(isset($mod['name']))
			{
				$MODULE_ROOT = MODULES_ROOT . $mod['name'] . '/';
			}
			if(isset($mod['file']))
			{
				require_once($MODULE_ROOT . $mod['file']);
			}
			$cl = $mod['class'];
			$module = call_user_func(array($cl, 'getInstance'));
			if(!$module)
			{
				echo "*** Failed to retrieve module instance ($cl)\n";
				exit(1);
			}
			echo ">>> Updating schema of " . $module->moduleId . "\n";
			if(!$module->setup())
			{
				echo "*** Schema update of " . $module->moduleId . " to " . $module->latestVersion . " failed\n";
				exit(1);
			}
			$MODULE_ROOT = $root;
		}
		echo ">>> Module setup completed successfully.\n";
	}
}
