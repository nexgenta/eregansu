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
 * @package EregansuLib Eregansu Core Library
 * @year 2010
 * @since Available in Eregansu 1.0 and later.
 * @task Processing requests
 */

require_once(dirname(__FILE__) . '/request.php');

/**
 * Implementation of the Request class for command-line (\x{cli}) requests.
 *
 * An instance of \C{CLIRequest} is returned by \m{Request::requestForSAPI}
 * if the current (or explicitly specified) SAPI is \x{cli}.
 *
 * @synopsis $req = Request::requestForSAPI('cli');
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
		$this->types = array('text/plain' => array('type' => 'text/plain', 'q' => 1));
	}
	
	/**
	 * Redirect a request to another location.
	 *
	 * Attempting to perform a redirect on the command-line causes the
	 * process to exit, because a 'redirect' in this context is
	 * nonsensical.
	 */
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
