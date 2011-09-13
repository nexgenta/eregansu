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
		if(!self::$typeMap)
		{
			foreach(self::$map as $mime => $exts)
			{
				self::$typeMap[$mime] = $exts[0];
			}
		}
		if(isset(self::$typeMap[$type])) return '.' . self::$typeMap[$type];
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
		if(!self::$extMap)
		{
			foreach(self::$map as $mime => $exts)
			{
				foreach($exts as $e)
				{
					if(!isset(self::$extMap[$e]))
					{
						self::$extMap[$e] = $mime;
					}
				}
			}
		}
		while(substr($ext, 0, 1) == '.') $ext = substr($ext, 1);
		if(isset(self::$extMap[$ext])) return self::$map[$ext];
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
	
	/**
	 * @internal
	 */
	protected static $map = array(
		'text/html' => array('html', 'mp', 'htm'),
		'text/plain' => array('text', 'txt'),
		'text/rtf' => array('rtf'),
		'text/xml' => array('xml'),
		'text/turtle' => array('ttl'),

		'application/xml' => array('xml'),
		'application/xhtml+xml' => array('xhtml'),
		'application/vnd.ctv.xhtml+xml' => array('ctv'),
		'application/vnd.hbbtv.xhtml+xml' => array('hbbtv'),
		'application/rss+xml' => array('rss'),
		'application/rdf+xml' => array('rdf'),
		'application/atom+xml' => array('atom'),		
		'application/vnd.sun.wadl+xml' => array('wadl'),

		'application/json' => array('json'),
		/* Note that application/x-jsonp is never sent; it's a pseudotype */
		'application/x-jsonp' => array('jsonp'),
		'application/ld+json' => array('jsonld'),
		'application/rdf+json' => array('rj', 'rdfjson'),
		'application/x-yaml' => array('yaml'),
		
		'application/x-pem-file' => array('pem'),
		'application/x-pkcs12' => array('pfx', 'p12'),
		'application/x-pkcs7-certificates' => array('p7b', 'spc'),
		'application/x-x509-ca-cert' => array('crt', 'der'),

		'application/mp4' => array('mp4'),
		'application/pgp-encrypted' => array('pgp'),
		'application/pgp-signature' => array('sig', 'asc'),

		'image/gif' => array('gif'),
		'image/jpeg' => array('jpeg', 'jpg'),
		'image/png' => array('png'),
		'image/tiff' => array('tiff', 'tif'),
		'image/jp2' => array('j2k', 'jp2'),
		'image/jpx' => array('jpx'),
		'image/jpm' => array('jpm'),
		
		'video/mp4' => array('m4v'),
		'video/3gpp' => array('3gpp', '3gp'),
		'video/3gpp2' => array('3gpp2'),
		'video/quicktime' => array('mov', 'qt'),
		'video/mp2t' => array('ts'),
		'video/x-flv' => array('flv'),
		'video/x-msvideo' => array('avi'),
		'video/x-ms-wmv' => array('wmv'),
		'video/x-ms-asf' => array('asf'),
		'video/vnd.ms-asf' => array('asf'),
		'video/mpeg' => array('mpeg', 'm2v', 'm1v', 'mpg'),
		'video/ogg' => array('ogv'),
		
		'audio/mp4' => array('m4a', 'm4b', 'm4p'),
		'audio/mpeg' => array('mp3', 'm3a', 'm2a', 'mp2'),
		'audio/ogg' => array('ogg', 'oga'),
	);
	/**
	 * @internal
	 */
	protected static $extMap;
	/**
	 * @internal
	 */
	protected static $typeMap;

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
		'x-pem-file' => 'PEM',
		'xml' => 'XML',
	);
}