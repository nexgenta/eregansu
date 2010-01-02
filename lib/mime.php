<?php

/* Eregansu: MIME type support
 *
 * Copyright 2009, 2010 Mo McRoberts.
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
 * @file lib/mime.php
 * @brief Support for MIME types
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 *
 * The \c mime module provides facilities for mapping file extensions to
 * MIME types and vice versa and obtaining human-readable descriptions
 * from MIME types.
 *
 * To make use of the MIME class, include \c 'mime' in your uses() call.
 */

/**
 * @class MIME
 * @since 1.0
 * @brief MIME type support
 *
 * @note Instances of the MIME class are never created; all methods are static.
 */
abstract class MIME
{
	/**
	 * @fn string extForType($type)
	 * @brief Return the preferred file extension for a specified MIME type
	 *
	 * MIME::extForType() returns the preferred file extension, if any, for a
	 * given MIME type. For example, the preferred extension string for the
	 * <code>'text/plain'</code> type is <code>'.txt'</code>.
	 *
	 * If a file extension mapping exists, it will be returned with a leading
	 * dot. If no file extension mapping exists, an empty string will be
	 * returned.
	 *
	 * @param[in] string $type The MIME type to resolve to an extension
	 * @returns The preferred file extension for \p $type, or an empty string if no mapping exists.
	 * @example mimetest.php	 
	 */
	public static function extForType($type)
	{
		if(!self::$extMap) self::$extMap = array_flip(self::$map);
		if(isset(self::$extMap[$type])) return '.' . self::$extMap[$type];
		return '';
	}
	
	/**
	 * @fn string typeForExt($ext)
	 * @brief Return the MIME type matching a specified file extension
	 *
	 * MIME::typeForExt() attempts to resolve a file extension to a MIME
	 * type.
	 *
	 * The file extension, \p $ext, may be specified with or without a
	 * leading dot.
	 *
	 * If the file extension could not be mapped to a MIME type, \c null
	 * is returned.
	 *
	 * @param[in] string $ext The file extension to resolve to a MIME type
	 * @returns The MIME type matching \p $ext, if it could be resolved, or \c null otherwise.
	 * @example mimetest.php	 
	 */
	public static function typeForExt($ext)
	{
		while(substr($ext, 0, 1) == '.') $ext = substr($ext, 1);
		if(isset(self::$map[$ext])) return self::$map[$ext];
		return null;
	}
	
	/**
	 * @fn string description($type)
	 * @brief Return a human-readable description of a MIME type
	 * 
	 * MIME::description() returns a human-readable description of a specified
	 * MIME type. For example, the description for <code>'video/mp4'</code>
	 * might be <code>'MPEG 4 video'</code>.
	 *
	 * @param[in] string $type The MIME type to obtain a description for
	 * @returns A human-readable description for \p $type
	 * @example mimetest.php	 
	 */
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
		if(isset(self::$prefix[$type[1]]))
		{
			$prefix = self::$prefix[$type[1]];
		}
		else
		{
			switch($type[1])
			{
				case 'm2ts':
					return 'MPEG Transport Stream';
				default:
					$prefix = '';
			}
		}
		return trim($prefix . ' ' . $suffix);
	}
	
	/* Because we use array_flip(), the last entry for a given MIME type
	 * specifies the preferred extension
	 */
	/**
	 * @cond INTERNAL
	 * @internal
	 * @hideinitializer
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
	/**
	 * @internal
	 */
	protected static $extMap;
	
	/**
	 * @hideinitializer
	 * @internal
	 */
	protected static $prefix = array(
		'plain' => 'text',
		'rtf' => 'rich text',
		'html' => 'HTML',
		'xhtml+xml' => 'HTML',
		'rss+xml' => 'RSS',
		'rdf+xml' => 'RDF',
		'atom+xml' => 'Atom',
		'json' => 'JSON',
		'x-yaml' => 'YAML',
		'gif' => 'GIF',
		'jpeg' => 'JPEG',
		'png' => 'PNG',
		'tiff' => 'TIFF',
		'jp2' => 'JPEG 2000',
		'jpx' => 'JPEG 2000',
		'mj2' => 'Motion JPEG 2000',
		'jpm' => 'JPEG 2000',
		'mp4' => 'MPEG 4',
		'mp3' => 'MPEG 1 Layer III',
		'vorbis' => 'Ogg Vorbis',
		'theora' => 'Ogg Theora',
	);
	/**
	 * @endcond
	 */
}