<?php

/* Eregansu: MIME type support
 *
 * Copyright 2009-2011 Mo McRoberts.
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
 * @framework EregansuCore Eregansu Core Library
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 * @year 2010
 * @copyright Mo McRoberts
 * @include uses('mime');
 * @sourcebase http://github.com/nexgenta/eregansu/blob/master/
 * @since Available in Eregansu 1.0 and later. 
 * @example mimetest.php	 
 */

/**
 * The \c{MIME} class provides facilities for mapping file extensions to
 * MIME types and vice versa and obtaining human-readable descriptions
 * from MIME types.
 *
 * <note>Instances of the \c{MIME} class are never created; all methods are static.</note>
 */
abstract class MIME
{
	/**
	 * @fn string extForType($type)
	 * @brief Return the preferred file extension for a specified MIME type
	 *
	 * \m{MIME::extForType} returns the preferred file extension, if any, for a
	 * given MIME type. For example, the preferred extension string for the
	 * \l{text/plain} type is \l{.txt}.
	 *
	 * If a file extension mapping exists, it will be returned with a leading
	 * dot. If no file extension mapping exists, an empty string will be
	 * returned.
	 *
	 * @param[in] string $type The MIME type to resolve to an extension
	 * @return string The preferred file extension for \p{$type}, or an empty string if no mapping exists.
	 */
	public static function extForType($type)
	{
		if(!self::$extMap) self::$extMap = array_flip(self::$map);
		if(isset(self::$extMap[$type])) return '.' . self::$extMap[$type];
		return '';
	}
	
	/**
	 * @brief Return the MIME type matching a specified file extension
	 *
	 * \m{MIME::typeForExt} attempts to resolve a file extension to a MIME
	 * type.
	 *
	 * The file extension, \p{$ext}, may be specified with or without a
	 * leading dot.
	 *
	 * If the file extension could not be mapped to a MIME type, \c{null}
	 * is returned.
	 *
	 * @param[in] string $ext The file extension to resolve to a MIME type
	 * @return string The MIME type matching \p{$ext}, if it could be resolved, or \c{null} otherwise.
	 */
	public static function typeForExt($ext)
	{
		while(substr($ext, 0, 1) == '.') $ext = substr($ext, 1);
		if(isset(self::$map[$ext])) return self::$map[$ext];
		return null;
	}
	
	/**
	 * @brief Return a human-readable description of a MIME type
	 * 
	 * \m{MIME::description} returns a human-readable description of a specified
	 * MIME type.
	 *
	 * For example, the description for \l{video/mp4} might be \l{MPEG 4 video}.
	 *
	 * @param[in] string $type The MIME type to obtain a description for
	 * @return string A human-readable description for \p{$type}
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
	 * @internal
	 */
	protected static $map = array(
		'htm' => 'text/html',
		'html' => 'text/html',
		'txt' => 'text/plain',
		'text' => 'text/plain',
		'rtf' => 'text/rtf',
		'xml' => 'text/xml',
		'ttl' => 'text/turtle',

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
	 * @internal
	 */
	protected static $prefix = array(
		'plain' => 'text',
		'rtf' => 'rich text',
		'html' => 'HTML',
		'xhtml+xml' => 'HTML',
		'rss+xml' => 'RSS',
		'rdf+xml' => 'RDF/XML',
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
		'turtle' => 'Turtle',
	);
}