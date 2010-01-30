<?php

/* Silk: A minature (toy) web server for development and testing
 *
 * Copyright 2010 Mo McRoberts.
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

if(!defined('SILK_PORT')) define('SILK_PORT', 8998);

class SilkCompleteException extends Exception
{
}

class Silk extends CommandLine
{
	const MAX_REQUEST_SIZE = 4096;
	
	protected $servers = array();
	protected $clients = array();
	protected $inbound = array();
	protected $done;
	
	public function main($args)
	{
		$dir = session_save_path();
		if(strlen($dir) && !is_writable($dir))
		{
			$ndir = sys_get_temp_dir();
			session_save_path($ndir);
			echo "silk: Warning: Session directory $dir is not writeable, using $ndir\n";
		}
		$this->listen(AF_INET, 'tcp', '0.0.0.0', SILK_PORT);
		$this->listen(AF_INET6, 'tcp6', '::', SILK_PORT);
		if(!count($this->servers))
		{
			echo "silk: Could not listen on any configured ports\n";
			exit(1);
		}
		echo "silk: Listening on port " . SILK_PORT . "\n";
		$this->done = false;
		while(!$this->done)
		{
			$read = array_merge($this->servers, $this->inbound);
			$write = null;
			$except = null;
			if(false === socket_select($read, $write, $except, 1))
			{
				echo "silk: select() failed\n";
				exit(1);
			}
			$status = null;
			while(pcntl_wait($status, WNOHANG) > 0);
			foreach($read as $socket)
			{
//				echo "silk: Activity on $socket\n";
				if(in_array($socket, $this->servers))
				{
					$this->accept($socket);
				}
				else if(in_array($socket, $this->inbound))
				{	
					foreach($this->clients as $k => $c)
					{
						if($c['socket'] == $socket)
						{
							$this->read($this->clients[$k]);
							break;						
						}
					}
				}
			}
		}
	}
	
	protected function listen($family, $protocol, $address, $port)
	{
		if(!($server = socket_create($family, SOCK_STREAM, getprotobyname($protocol))))
		{
			echo "silk: Failed to listen on ($protocol) $address on port $port\n";
			return false;
		}
		socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($server, $address, $port);
		socket_listen($server, 5);
		$this->servers[] = $server;
	}
	
	protected function accept($socket)
	{
		if(!($client = socket_accept($socket)))
		{
			echo "silk: Warning: Failed to accept socket from #$socket\n";
			return;
		}
		$addr = null;
		$port = null;
		socket_getpeername($client, $addr, $port);
		$this->inbound[] = $client;
		$this->clients[] = array(
			'socket' => $client,
			'address' => $addr,
			'port' => $port,
			'buffer' => '',
			'size' => 0,			
			'method' => null,
			'resource' => null,
			'protocol' => null,
			'headers' => array(),
		);
	}
	
	protected function read(&$client)
	{
		if(false === ($byte = socket_read($client['socket'], 1)))
		{
			$this->close($client);
			return;
		}
//		echo "Read [$byte]\n";
		$client['buffer'] .= $byte;
		$client['size']++;
		if($client['size'] >= self::MAX_REQUEST_SIZE)
		{
			echo "Request exceeds maximum size, aborting\n";
			$this->close($client);
			return;
		}
		if($byte == "\n")
		{
			$this->parse($client);
		}
	}

	protected function close($client)
	{
		foreach($this->clients as $k => $c)
		{
			if($c['socket'] == $client['socket'])
			{
				unset($this->clients[$k]);
				break;
			}
		}
		foreach($this->inbound as $k => $c)
		{
			if($c == $client['socket'])
			{
				unset($this->inbound[$k]);
				break;
			}
		}
		socket_close($client['socket']);
	}
	
	protected function parse(&$client)
	{
		$lines = explode("\n", $client['buffer']);
//		print_r($lines);
		if($client['method'] === null)
		{
			$req = explode(" ", array_shift($lines));
			if(count($req) != 3)
			{
				echo "Bad request\n";
				$this->close($client);
				return;
			}
			$client['method'] = $req[0];
			$client['resource'] = $req[1];
			$client['protocol'] = $req[2];
		}
		else
		{
			array_shift($lines);
		}
		if(count($lines) < 2) return;
		$line = trim($lines[count($lines) - 1]);
		$line2 = trim($lines[count($lines) - 2]);
		if(!strlen($line) && !strlen($line2))
		{
			array_pop($lines);
			array_pop($lines);
			foreach($lines as $line)
			{
				$line = explode(':', rtrim($line), 2);
				$client['headers'][strtolower($line[0])] = ltrim($line[1]);
			}
			$pid = pcntl_fork();
			if($pid == 0)
			{
				$this->done = true;
				$this->closeExcept($client);
				$this->processRequest($client);
			}
			else if($pid > 0)
			{
				$this->close($client);
			}
			else
			{
				echo "silk: Error: Failed to fork()\n";
				$this->close($client);
			}
		}
	}
	
	protected function closeExcept(&$client)
	{
		foreach($this->servers as $v)
		{
			socket_close($v);
		}
		if(is_array($this->inbound))
		foreach($this->inbound as $v)
		{
			if($v != $client['socket'])
			{
				socket_close($v);
			}
		}
		$this->servers = $this->client = $this->inbound = array();
	}
	
	protected function processRequest(&$client)
	{
		ini_set('display_errors', 'On');
		$res = explode('?', $client['resource'], 2);
		$pl = explode('/', $client['resource']);
		$path = array();
		foreach($pl as $p)
		{
			if(!strlen($p) || !strcmp($p, '.')) continue;
			if($p == '..')
			{
				array_pop($path);
			}
			else
			{
				$path[] = $p;
			}
		}
		
		$path = implode('/', $path);
		$client['resource'] = '/' . $path;
		$client['query'] = (isset($res[1]) ? $res[1] : null);
		$local = INSTANCE_ROOT . $path;
		if(file_exists($local) && !is_dir($local))
		{
			socket_write($client['socket'], "HTTP/1.0 200 OK\nConnection: close\n\n" . file_get_contents($local));
			socket_close($client['socket']);
			exit(0);
		}
		Error::$throw = true;
		require_once(dirname(__FILE__) . '/request.php');
		$req = new SilkRequest($client);
		$app = App::initialApp('silk');
		ob_start();
		try
		{
			$app->process($req);
		}
		catch(Exception $e)
		{
			if(!($e instanceof TerminalErrorException) && !($e instanceof SilkCompleteException))
			{
				echo $e . "\n";
			}
		}
		$req->flush();
		while(ob_get_level() > 1) ob_end_flush();
		if(ob_get_level())
		{
			$req->write(ob_get_clean());
		}
		exit(0);
	}
}
