<?php

/**
 * @year 2010
 * @include uses('uuid');
 * @since Available in Eregansu 1.0 and later. 
 * @example examples/uuids.php
 * @package EregansuLib Eregansu Core Library
 */

/* Copyright 2010 Mo McRoberts.
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
 * Abstract class containing UUID manipulation facilities.
 *
 * The \class{UUID} class contains facilities for generating and manipulating
 * Universally Unique Identifiers (UUIDs), according to 
 * \link{http://www.ietf.org/rfc/rfc4122.txt|RFC 4122} (equivalent to
 * ITU-T Rec. X.667, ISO/IEC 9834-8:2005).
 *
 * @note Instances of the UUID class are never created; all methods are static.
 */
abstract class UUID
{
	const UNKNOWN = -1; /**< Unknown UUID version or variant */
	
	const NONE = 0; /**< Version 0 (NULL UUID) */
	const DCE_TIME = 1; /**< Version 1 (MAC address) */
	const DCE_SECURITY = 2; /**< Version 2 (DCE Security) */
	const HASH_MD5 = 3; /**< Version 3 (MD5 hash) */
	const RANDOM = 4; /**< Version 4 (Random) */
	const HASH_SHA1 = 5; /**< Version 5 (SHA1 hash) */
	
	const NCS = 0; /**< Apollo NCS variant UUID */
	const DCE = 1; /**< OSF DCE variant UUID */
	const MICROSOFT = 2; /**< Microsoft variant GUID */
	const RESERVED = 3; /**< Reserved for future use */
	
	const DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; /**< Namespace UUID for DNS identifiers */
	const URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8'; /**< Namespace UUID for URL identifiers */
	const OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8'; /**< Namespace UUID for ISO OID identifiers */
	const DN = '6ba7b814-9dad-11d1-80b4-00c04fd430c8'; /**< Namespace UUID for X.500 DNs */
	
	/**
	 * @brief Generate a new UUID
	 * @task Generating UUIDs
	 *
	 * \m{UUID::generate} generates a new UUID according to \link{http://www.ietf.org/rfc/rfc4122.txt|RFC 4122} (equivalent to
	 * ITU-T Rec. X.667, ISO/IEC 9834-8:2005).
	 *
	 * If the kind of UUID specified by \p{$kind} cannot be generated
	 * because it is not supported, a random (v4) UUID will be generated instead (in other
	 * words, the \p{$kind} parameter is a hint).
	 *
	 * If the kind of UUID specified by \p{$kind} cannot be generated
	 * because one or both of \p{$namespace} and \p{$name}
	 * are not valid, an error occurs and \c{null} is returned.
	 *
	 * @type string
	 * @param[in,optional] int $kind The kind of UUID to generate.
	 * @param[in,optional] string $namespace For MD5 (v3) and SHA1 (v5) UUIDs, the namespace which contains \p{$name}.
	 * @param[in,optional] string $name For MD5 (v3) and SHA1 (v5) UUIDs, the identifier used to generate the UUID.	
	 * @return A new UUID, or \c{null} if an error occurs.
	 */
	public static function generate($kind = self::RANDOM, $namespace = null, $name = null)
	{
		switch($kind)
		{
		case self::UNKNOWN:
			return null;
		case self::NONE:
			return self::nil();
		case self::HASH_MD5:
		case self::HASH_SHA1:
			if($namespace !== null && $name !== null)
			{
				return self::hash($namespace, $name, $kind);
			}
		default:
			/* Generate a random (version 4) UUID if all else fails */
			return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
						   mt_rand(0, 0xffff), mt_rand(0, 0xffff),
						   mt_rand(0, 0xffff),
						   mt_rand(0, 0x0fff) | 0x4000,
						   mt_rand(0, 0x3fff) | 0x8000, 
						   mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
		}		
	}
	
	protected static function hash($namespace, $name, $version)
	{
		$namespace = self::canonical($namespace);
		$nsdata = pack('H*', $namespace);
		if($version == self::HASH_MD5)
		{
			$hash = md5($nsdata . $name, true);
		}
		else
		{
			$hash = sha1($nsdata . $name, true);
		}
		$result = unpack('Ntime_low/ntime_mid/ntime_hi_and_version/Cclock_seq_hi_and_reserved/Cclock_seq_low/C*', $hash);		
		$result['time_hi_and_version'] &= 0x0FFF;
		$result['time_hi_and_version'] |= ($version << 12);
		$result['clock_seq_hi_and_reserved'] &= 0x3F;
		$result['clock_seq_hi_and_reserved'] |= 0x80;
		$out = sprintf('%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x',
					   $result['time_low'], $result['time_mid'],
					   $result['time_hi_and_version'],
					   $result['clock_seq_hi_and_reserved'], $result['clock_seq_low'],
					   $result[1], $result[2], $result[3], $result[4],
					   $result[5], $result[6]);
		return $out;
	}

	/**
	 * @brief Return the null UUID as a string
	 * @task Generating UUIDs
	 *
	 * \m{UUID::nil} returns a string containing the null UUID.
	 *
	 * It is the equivalent of calling <code>\m{UUID::generate}(\c{UUID::NONE});</code>
	 *
	 * @return string The null UUID. i.e., \x{00000000-0000-0000-0000-000000000000}.
	 */
	public static function nil()
	{
		return '00000000-0000-0000-0000-000000000000';
	}

	/**
	 * @brief Determine whether a string is a valid UUID or not
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::isUUID} tests whether a string consists of a valid UUID.
	 *
	 * @type string
	 * @param[in] string $str The string that is potentially a UUID.
	 * @return If \p{$str} is a UUID, then the return value is \p{$str},
	 *   otherwise \c{null} is returned.
	 */
	public static function isUUID($str)
	{
		if(preg_match('/^(urn:uuid:)?\{?[a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{12}\}?$/i', $str))
		{
			return $str;
		}
		return null;
	}
	
	/**
	 * @brief Return the canonical form of a UUID string (i.e., no braces, no dashes, all lower-case)
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::canonical} accepts a string representation of a UUID (for example, as returned by
	 * \m{UUID::generate}) and returns the canonical form of the UUID: that is, all-lowercase, and with
	 * any braces and dashes removed.
	 *
	 * For example, the canonical form of the UUID string \x{|{EAE58635-B826-42A9-9B03-3A3AC8A2CC29}|}
	 * would be \x{'eae58635b82642a99b033a3ac8a2cc29'}.
	 *
	 * @type string
	 * @param[in] string $uuid A string representation of a UUID.
	 * @return The canonical form of the UUID, or \c{null} if \p{$uuid} is not a valid UUID string.
	 */
	public static function canonical($uuid)
	{
		$uuid = strtolower(trim(str_replace(array('-','{','}'), '', $uuid)));
		if(!strncmp($uuid, 'urn:uuid:', 9)) $uuid = substr($uuid, 9);
		if(strlen($uuid) != 32) return null;
		if(!ctype_xdigit($uuid)) return null;
		return $uuid;
	}
	
	/**
	 * @brief Formats a UUID as an IRI
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::iri} converts a string representation of a UUID to an IRI
	 * (Internationalized Resource Identifier), specifically a UUID URN.
	 *
	 * For example, the null UUID converted to an IRI would be \x{urn:uuid:00000000-0000-0000-0000-000000000000}.
	 *
	 * @type string
	 * @param[in] string $uuid A string representation of a UUID
	 * @return The IRI representation of \p{$uuid}, or \c{null} if \p{$uuid} is not a valid UUID string.
	 */
	public static function iri($uuid)
	{
		if(!($uuid = self::canonical($uuid)))
		{
			return null;
		}
		return 'urn:uuid:' . substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12);
	}

	/**
	 * @brief Format a UUID in the traditional aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee form
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::iri} converts a string representation of a UUID in the
	 * traditional form.
	 *
	 * For example, the null UUID converted to an IRI would be \x{00000000-0000-0000-0000-000000000000}.
	 *
	 * @type string
	 * @param[in] string $uuid A string representation of a UUID
	 * @param[in,optional] $prefix An optional string to prepend to the formatted UUID.
	 * @param[in,optional] $suffix An optional string to append to the formatted UUID.
	 * @return The IRI representation of \p{$uuid}, or \c{null} if \p{$uuid} is not a valid UUID string.
	 */
	public static function formatted($uuid, $prefix = null, $suffix = null)
	{
		if(!($uuid = self::canonical($uuid)))
		{
			return null;
		}
		return $prefix . substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12) . $suffix;
	}
	
	/**
	 * @brief Parse a string containing a UUID and return an array representing its value.
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::parse} converts a string representation of a UUID to an array. The
	 * array contains the following members:
	 *
	 * - \x{time_low}
	 * - \x{time_mid}
	 * - \x{time_hi_and_version}
	 * - \x{clock_seq_hi_and_reserved}
	 * - \x{clock_seq_low}
	 * - \x{node}
	 * - \x{version}
	 * - \x{variant}
	 *
	 * The \x{version} member contains a UUID version number, for example \c{UUID::RANDOM}.
	 * The \x{variant} member specifies the UUID variant, for example \c{UUID::DCE}.
	 *
	 * @type array
	 * @param[in] string $uuid A string representation of a UUID.
	 * @return An array representing the supplied UUID, or \c{null} if an error occurs.
	 */
	public static function parse($uuid)
	{
		if(!($uuid = self::canonical($uuid)))
		{
			return null;
		}
		$info = array(
			'time_low' => '', 
			'time_mid' => '',
			'time_hi_and_version' => '',
			'clock_seq_hi_and_reserved' => '',
			'clock_seq_low' => '',
			'node' => '',
			'version' => self::UNKNOWN,
			'variant' => self::UNKNOWN,
			);
		sscanf($uuid, '%8x%4x%4x%2x%2x%12s', $info['time_low'], $info['time_mid'], $info['time_hi_and_version'], $info['clock_seq_hi_and_reserved'], $info['clock_seq_low'], $info['node']);
		$info['version'] = ($info['time_hi_and_version'] & 0xF000) >> 12;
		if(($info['clock_seq_hi_and_reserved'] & 0xC0) == 0x80)
		{
			$info['variant'] = self::DCE;
		}
		else if(($info['clock_seq_hi_and_reserved'] & 0xE0) == 0xC0)
		{
			$info['variant'] = self::MICROSOFT;
		}
		else if(($info['clock_seq_hi_and_reserved'] & 0xE0) == 0xE0)
		{
			$info['variant'] = self::RESERVED;
		}
		return $info;
	}
	
	/**
	 * @brief Constructs a UUID string given an array as returned by UUID::parse()
	 * @task Manipulating UUIDs
	 *
	 * \m{UUID::unparse} accepts an array representation of a UUID as returned by
	 * \m{UUID::parse} and returns a string representation of the same UUID.
	 *
	 * @type string
	 * @param[in] array $info An array representation of a UUID
	 * @return A string representing the supplied UUID
	 */
	public static function unparse($info)
	{
		return sprintf('%08x-%04x-%04x-%02x%02x-%12s', $info['time_low'] & 0xFFFFFFFF, $info['time_mid'], $info['time_hi_and_version'], $info['clock_seq_hi_and_reserved'], $info['clock_seq_low'], strtolower($info['node']));
	}
}
