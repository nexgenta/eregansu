<?php

/* Eregansu: Command-line request support
 *
 * Copyright 2009 Mo McRoberts.
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
 * @framework EregansuCore Eregansu Core Library
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 * @year 2010
 * @copyright Mo McRoberts
 * @sourcebase http://github.com/nexgenta/eregansu/blob/master/
 * @since Available in Eregansu 1.0 and later. 
 */

require_once(dirname(__FILE__) . '/request.php');

/**
 * @class CLIRequest
 * @brief Implementation of the Request class for command-line (<code>cli</code>) requests.
 */
class CLIRequest extends Request
{
	protected function init()
	{
		parent::init();
		$this->method = '__CLI__';
		$this->params = $_SERVER['argv'];
		array_shift($this->params);
		if(isset($this->params[0]) && substr($this->params[0], 0, 1) == '@')
		{
			$this->hostname = substr($this->params[0], 1);
			array_shift($this->params);
		}
	}
	
	protected function determineTypes($acceptHeader = null)
	{
		$this->types = array('text/plain');
	}
	
	public function redirect()
	{
		exit();
	}
	
	protected function beginSession()
	{
		$this->beginTransientSession();
		if($this->sessionInitialised)
		{
			call_user_func($this->sessionInitialised, $this, $this->session);
		}
	}
}
