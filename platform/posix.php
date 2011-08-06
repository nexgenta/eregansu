<?php

/* Eregansu: Authentication — Posix pseudo-authentication engine
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
 * @year 2009
 * @include uses('auth');
 * @since Available in Eregansu 1.0 and later. 
 */

/**
 * Implementation of the \x{posix:} pseudo-authentication scheme.
 */
class PosixAuth extends Auth
{
	protected $builtinAuthScheme = false;
	protected $users = array();
	
	public function retrieveUserData($scheme, $uid)
	{
		if(!is_numeric($uid))
		{
			if(!($info = posix_getpwnam($uid)))
			{
				return null;
			}
			$uid = $info['uid'];
		}
		$uid = intval($uid);
		if($uid < 0)
		{
			$uid = 65536 - $uid;
		}
		if(isset($this->users[$uid])) return $this->users[$uid];
		if(!($info = posix_getpwuid($uid)))
		{
			$info = array();
		}
		$info['scheme'] = 'posix';
		$info['uuid'] = '00000000-0000-0000-0000-0000' . sprintf('%08d', $uid);
		$this->users[$uid] = $info;
		return $info;
	}
}