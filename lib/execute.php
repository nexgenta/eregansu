<?php

/* Copyright 2009, 2010 Mo McRoberts.
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