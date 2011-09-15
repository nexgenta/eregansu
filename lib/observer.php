<?php

/* Eregansu: Observable objects
 *
 * Copyright 2001, 2010 Mo McRoberts.
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

interface IObservable
{
}

abstract class Observers
{
	private static $callbacks = array();

	public static function observe($event, $callback, $data = null)
	{
		self::$callbacks[$event][] = array($callback, $data);
		if(!is_callable($callback))
		{
			trigger_error('Callback ' . $callback . ' registered for event ' . $event . ' is not a valid callback', E_USER_WARNING);
		}
	}

	public static function forget($event, $callback)
	{
		if(!isset(self::$callbacks[$event]))
		{
			return;
		}
		foreach(self::$callbacks[$event] as $k => $cb)
		{
			if($cb[0] === $callback)
			{
				unset(self::$callbacks[$event]);
			}
		}
	}
	
	public static function invoke($event, IObservable $observable, $args = null)
	{
		if(!isset(self::$callbacks[$event]))
		{
			return;
		}
		foreach(self::$callbacks[$event] as $cb)
		{
			call_user_func($cb[0], $observable, $args, $cb[1]);
		}
	}
}
