<?php

/* Copyright 2009, 2010 Mo McRoberts.
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
 * @file lib/execute.php
 * @brief Process spawning support
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 */

function execute($prog, $args = null, $captureOutput = true)
{
	$result = array('status' => -1, 'stdout' => null, 'stderr' => null);
	if($captureOutput)
	{
		$spec = array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w'),
		);
	}
	else
	{
		$spec = array();
	}
	$pipes = array();
	
	$cmdline = escapeshellcmd($prog);
	if(is_array($args))
	{
		foreach($args as $arg)
		{
			$cmdline .= ' ' . escapeshellarg($arg);
		}
	}
	else if(strlen($args))	
	{
		$cmdline .= $args;
	}
	$proc = proc_open($cmdline, $spec, $pipes);
	if(!$captureOutput)
	{
		while(true)
		{
			$info = proc_get_status($proc);
			if(empty($info['running']))
			{
				$result['status'] = $info;
				break;
			}
			usleep(250);
		}
	}
	else
	{
		if(isset($pipes[1]) && is_resource($pipes[1]))
		{
			$result['stdout'] = stream_get_contents($pipes[1]);
		}
		if(isset($pipes[2]) && is_resource($pipes[2]))
		{
			$result['stderr'] = stream_get_contents($pipes[2]);
		}
		if(isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
		if(isset($pipes[1]) && is_resource($pipes[1])) fclose($pipes[1]);
		if(isset($pipes[2]) && is_resource($pipes[2])) fclose($pipes[2]);
		$result['status'] = proc_get_status($proc);
	}
	proc_close($proc);
	return $result;
}