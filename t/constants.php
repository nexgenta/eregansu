<?php

/* Copyright 2011 Mo McRoberts.
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

class TestConstants extends TestHarness
{
	public function main()
	{
		if(!$this->check('__EREGANSU__')) return false;
		if(!$this->expectPath('INSTANCE_ROOT', dirname(__FILE__) . '/../testsuite')) return false;
		if(!$this->expectPath('PUBLIC_ROOT', INSTANCE_ROOT)) return false;
		if(!$this->expectPath('PLATFORM_ROOT', dirname(__FILE__) . '/../')) return false;
		if(!$this->expectPath('PLATFORM_LIB', PLATFORM_ROOT . 'lib/')) return false;
		if(!$this->expectPath('PLATFORM_PATH', PLATFORM_ROOT . 'platform/')) return false;
		if(!$this->expectPath('CONFIG_ROOT', INSTANCE_ROOT . 'config/')) return false;
		if(!$this->expectPath('MODULES_ROOT', INSTANCE_ROOT . 'app/')) return false;
		if(!$this->expectPath('PLUGINS_ROOT', INSTANCE_ROOT . 'plugins/')) return false;
		return true;
	}

	protected function check($name)
	{
		if(!defined($name))
		{
			echo $name . " is not defined.\n";
			return false;
		}
		echo $name . " => " . constant($name) . "\n";
		return true;
	}

	protected function expect($name, $value)
	{
		if(!$this->check($name))
		{
			return false;
		}
		if(strcmp(constant($name), $value))
		{
			echo $name . " does not match expected value ('" . $value . "')\n";
			return false;
		}
		return true;
	}

	protected function expectPath($name, $value)
	{
		if(!$this->check($name))
		{
			return false;
		}
		$p1 = realpath(constant($name));
		$p2 = realpath($value);
		if(strcmp($p1, $p2))
		{
			echo $name . " ('" . $p1 . "') does not match expected value ('" . $p2 . "')\n";
			return false;
		}
		return true;
	}

}

return 'TestConstants';
