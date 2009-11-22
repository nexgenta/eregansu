<?php

/* Eregansu: MIME type support
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


class MIME
{
	public static function extForType($type)
	{
		if(!self::$extMap) self::$extMap = array_flip(self::$map);
		if(isset(self::$extMap[$type])) return '.' . self::$extMap[$type];
		return '';
	}
	
	public static function typeForExt($ext)
	{
		while(substr($ext, 0, 1) == '.') $ext = substr($ext, 1);
		if(isset(self::$map[$ext])) return self::$map[$ext];
		return '';
	}
	
	public static function description($type)
	{
		if(!is_array($type)) $type = explode('/', $type);
		switch($type[0])
		{
			case 'image':
				$suffix = 'image';
				break;
			case 'video':
				$suffix = 'video';
				break;
			case 'audio':
				$suffix = 'audio';
				break;
			case 'text':
				$suffix = 'document';
				break;
			default:
				$suffix = 'file';
		}
		switch($type[1])
		{
			case 'png':
				$prefix = 'PNG';
				break;
			case 'jpeg':
				$prefix = 'JPEG';
				break;
			case 'tiff':
				$prefix = 'TIFF';
				break;
			case 'mp4':
				$prefix = 'MPEG 4';
				break;
			case 'm2ts':
				$prefix = 'MPEG Transport Stream';
				break;
			case 'mp3':
				$prefix = 'MPEG 1 Layer III';
				break;
			case 'vorbis':
				$prefix = 'Ogg Vorbis';
				break;
			case 'theora':
				$prefix = 'Ogg Theora';
				break;
			default:
				$prefix = '';
		}
		return trim($prefix . ' ' . $suffix);
	}
	
	/* Because we use array_flip(), the last entry for a given MIME type
	 * specifies the preferred extension
	 */
	protected static $map = array(
		'htm' => 'text/html',
		'html' => 'text/html',
		'txt' => 'text/plain',
		'text' => 'text/plain',
		'rtf' => 'text/rtf',
		'xml' => 'text/xml',

		'xhtml' => 'application/xhtml+xml',
		'rss' => 'application/rss+xml',
		'rdf' => 'application/rdf+xml',
		'atom' => 'application/atom+xml',
		'json' => 'application/json',
		'yaml' => 'application/x-yaml',
		'mp4' => 'application/mp4',

		'gif' => 'image/gif',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'tif' => 'image/tiff',
		'tiff' => 'image/tiff',
		'jp2' => 'image/jp2',
		'j2k' => 'image/jp2',
		'jpx' => 'image/jpx',
		'jpm' => 'image/jpm',

		'm4v' => 'video/mp4',

		'm4p' => 'audio/mp4',
		'm4b' => 'audio/mp4',
		'm4a' => 'audio/mp4',
	);	
	protected static $extMap;
}