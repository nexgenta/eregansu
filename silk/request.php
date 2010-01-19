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

class SilkRequest extends HTTPRequest
{
	protected $headersWritten = false;
	protected $socket;
	protected $cookies = array();
	protected $headers = array(
		'connection' => array('Connection: close'),
		'content-type' => array('Content-type: text/html; charset=UTF-8'),
		'server' => array('Server: Eregansu Silk'),
		);
	
	public function __construct($client)
	{
		$this->socket = $client['socket'];
		$fn = $_SERVER['SCRIPT_FILENAME'];
		$_REQUEST = $_GET = $_POST = $_COOKIE = $_SERVER = $_FILES = array();
		$_SERVER['QUERY_STRING'] = $client['query'];
		$_SERVER['SERVER_PROTOCOL'] = $client['protocol'];
		$_SERVER['REQUEST_URI'] = $client['resource'];
		$_SERVER['REQUEST_METHOD'] = $client['method'];
		$_SERVER['DOCUMENT_ROOT'] = INSTANCE_ROOT;
		$_SERVER['PHP_SELF'] = '/' . basename($fn);
		$_SERVER['SCRIPT_NAME'] = '/' . basename($fn);
		$_SERVER['SCRIPT_FILENAME'] = realpath($fn);
		if(isset($client['headers']['cookie']))
		{
			$cookie = explode(';', $client['headers']['cookie']);
			foreach($cookie as $c)
			{
				$c = explode('=', trim($c), 2);
				if(!isset($c[1])) $c[1] = '';
				$_COOKIE[$c[0]] = $c[1];
				$_REQUEST[$c[0]] = $c[1];
			}
		}
		$headers = array(
			'HTTP_HOST' => 'host',
			'HTTP_USER_AGENT' => 'user-agent',
			'HTTP_ACCEPT' => 'accept',
			'HTTP_CONTENT_TYPE' => 'content-type',
			'HTTP_CONTENT_LENGTH' => 'content-length',
		);
		foreach($headers as $h => $v)
		{
			unset($_SERVER[$h]);
			if(isset($client['headers'][$v])) $_SERVER[$h] = $client['headers'][$v];
		}
		if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_CONTENT_TYPE']) && !strcmp($_SERVER['HTTP_CONTENT_TYPE'], 'application/x-www-form-urlencoded') && !empty($_SERVER['HTTP_CONTENT_LENGTH']))
		{
			$data = socket_read($this->socket, $_SERVER['HTTP_CONTENT_LENGTH']);
			parse_str($data, $_POST);
		}
		parent::__construct();
	}
	
	public function write($str)
	{
		if(!$this->headersWritten) $this->writeHeaders();
		socket_write($this->socket, $str);
	}
	
	public function flush()
	{
		if(!$this->headersWritten) $this->writeHeaders();
		parent::flush();
	}
	
	public function header($name, $value, $replace = true)
	{
		if($this->headersWritten)
		{
			trigger_error('Cannot modify header information - headers already sent', E_USER_WARNING);
			return false;
		}
		$name = trim($name);
		$k = strtolower($name);
		if($k == 'status')
		{
			$name = '';
		}
		else
		{
			$name .= ': ';
		}
		if($replace)
		{
			$this->headers[$k] = array($name . $value);
		}
		else
		{
			$this->headers[$k][] = $name . $value;
		}
	}
	
	public function setCookie($name, $value = null, $expires = 0, $path = null, $domain = null, $secure = false, $httponly = false)
	{
		if($this->headersWritten)
		{
			trigger_error('Cannot modify header information - headers already sent', E_USER_WARNING);
			return false;
		}
		$v = array(urlencode($name) . '=' . urlencode($value));
		if($expires)
		{
			$v[] = 'expires=' . strftime('%a, %e-%b-%Y %H:%M:%S UTC', $expires);
		}
		if($path)
		{
			$v[] = 'path=' . $path;
		}
		if($domain)
		{
			$v[] = 'domain=' . $domain;
		}
		$this->cookies[$name] = implode('; ', $v);
	}
	
	protected function writeHeaders()
	{
		if(!isset($this->headers['status']))
		{
			if(isset($this->headers['location']))
			{
				$this->headers['status'] = array('HTTP/1.0 302 Moved temporarily');
			}
			else
			{
				$this->headers['status'] = array('HTTP/1.0 200 OK');
			}
		}
		$buf = $this->headers['status'][0] . "\n";
		foreach($this->headers as $k => $v)
		{
			if($k == 'status') continue;
			$buf .= implode("\n", $v) . "\n";
		}
		foreach($this->cookies as $v)
		{
			$buf .= 'Set-Cookie: ' . $v . "\n";
		}
		socket_write($this->socket, $buf . "\n");
		$this->headersWritten = true;
	}
	
	public function complete()
	{
		throw new SilkCompleteException();
	}

	public function abort()
	{
		throw new TerminateErrorException();
	}
}