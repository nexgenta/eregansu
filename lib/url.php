<?php

/* Eregansu: Additional stream wrapper support (copying, symbolic links)
 *
 * Copyright 2009 Mo McRoberts.
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
 
/* The global $VFS array is associative, where the key is the name of the URL
 * scheme and the value is the name of the class implementing the scheme.
 * The named classes are registered as PHP stream wrappers. PHP doesn’t provide
 * a means to retrieve the class registered as a wrapper for a given scheme,
 * so we must look it up in $VFS instead. This obviously won’t work if the
 * wrapper class doesn’t have an entry in $VFS.
 */
 
abstract class URL
{
	protected static $registered = false;
	
	public static function register()
	{
		global $VFS;
		
		if(self::$registered)
		{
			return;
		}
		if(isset($VFS) && is_array($VFS))
		{
			foreach($VFS as $scheme => $class)
			{
				stream_wrapper_register($scheme, $class, STREAM_IS_URL);
			}
		}
		self::$registered = true;		
	}
	
	public static function parse($url)
	{
		$match = array();
		if(preg_match('!(^[a-z0-9+-]+)://(/[^?#]*)?(\?[^#]*)?(#.*)?$!', $url, $match))
		{
			$info = array('scheme' => $match[1], 'host' => null, 'path' => null, 'query' => null, 'fragment' => null);
			if(isset($match[2])) $info['path'] = $match[2];
			if(isset($match[3])) $info['query'] = $match[3];
			if(isset($match[4])) $info['fragment'] = $match[4];
			return $info;
		}
		return parse_url($url);
	}

	public static function copy($source, $dest, $context = null)
	{
		global $VFS;
		
		self::register();
		if(($info = @self::parse($source)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'recvFile'))
			{
				$inst->context = $context;
				return $inst->recvFile($source, $dest);
			}
		}
		if(($info = @self::parse($dest)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'sendFile'))
			{
				$inst->context = $context;
				return $inst->sendFile($source, $dest);
			}
		}
		/* Fall back to PHP’s own implementation */
		return copy($source, $dest);
	}
	
	public static function readlink($path, $context = null)
	{
		global $VFS;

		self::register();		
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'readlink'))
			{
				$inst->context = $context;
				return $inst->readlink($path);
			}
		}
		return readlink($path);
	}

	public static function realpath($path, $context = null)
	{
		global $VFS;
		
		self::register();		
		if(($info = @self::parse($path)) && isset($info['scheme']) && strlen($info['scheme']) && isset($VFS[$info['scheme']]))
		{
			$inst = new $VFS[$info['scheme']];
			if(method_exists($inst, 'realpath'))
			{
				$inst->context = $context;
				return $inst->realpath($path);
			}
		}
		return realpath($path);
	}
	
}
 