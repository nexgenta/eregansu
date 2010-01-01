<?php

/* Copyright 2010 Mo McRoberts.
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
 * @file lib/uuid.php
 * @brief Implements the UUID class
 *
 * The \c uuid module provides facilities for generating and manipulating UUIDs,
 * implemented by way of the UUID class.
 *
 * To make use of the UUID class, include \c 'uuid' in your uses() call.
 *
 * @example uuidtest.php
 */

/**
 * @class UUID
 * @brief Abstract class containing UUID manipulation facilities
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
	 * @fn string generate($kind = self::RANDOM, $namespace = null, $name = null)
	 * @brief Generate a new UUID
	 *
	 * UUID::generate() generates a new UUID according to <a href="http://www.ietf.org/rfc/rfc4122.txt">RFC 4122</a> (equivalent to
	 * ITU-T Rec. X.667, ISO/IEC 9834-8:2005).
	 *
	 * If the kind of UUID specified by \p $kind cannot be generated because it is
	 * not supported, a random (v4) UUID will be generated instead (in other words,
	 * the \p $kind parameter is a hint).
	 *
	 * If the kind of UUID specified by \p $kind cannot be generated because one or
	 * both of \p $namespace and \p $name are not valid, an error occurs and \p null
	 * is returned.
	 *
	 * @param[in] int $kind The kind of UUID to generate.
	 * @param[in] string $namespace For MD5 (v3) and SHA1 (v5) UUIDs, the namespace which contains \p $name.
	 * @param[in] string $name For MD5 (v3) and SHA1 (v5) UUIDs, the identifier used to generate the UUID.
	 * @returns A new UUID composed as a string, or \c null if an error occurs.
	 * @example uuidtest.php
	 */
	public static function generate($kind = self::RANDOM, $namespace = null, $name = null)
	{
		switch($kind)
		{
			case self::UNKNOWN:
				return null;
			case self::NONE:
				return self::nil();
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
	
	/**
	 * @fn string nil()
	 * @brief Return the null UUID as a string
	 *
	 * UUID::nil() returns a string containing the null UUID.
	 *
	 * It is the equivalent of calling \c UUID::generate(UUID::NONE);
	 *
	 * @returns The null UUID as a string. i.e., <code>'00000000-0000-0000-0000-000000000000'</code>.
	 * @example uuidtest.php	 
	 */
	public static function nil()
	{
		return '00000000-0000-0000-0000-000000000000';
	}
	
	/**
	 * @fn string canonical($uuid)
	 * @brief Return the canonical form of a UUID string (i.e., no braces, no dashes, all lower-case)
	 *
	 * UUID::canonical() accepts a string representation of a UUID (for example, as returned by
	 * UUID::generate()) and returns the canonical form of the UUID: that is, all-lowercase, and with
	 * any braces and dashes removed.
	 *
	 * For example, the canonical form of the UUID string <code>'{EAE58635-B826-42A9-9B03-3A3AC8A2CC29}'</code>
	 * would be <code>'eae58635b82642a99b033a3ac8a2cc29'</code>.
	 *
	 * @param[in] string $uuid A string representation of a UUID.
	 * @returns A string containing the canonical form of the UUID, or \c null if \p $uuid is not a valid UUID string.
	 * @example uuidtest.php
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
	 * @fn string iri($uuid)
	 * @brief Formats a UUID as an IRI
	 *
	 * UUID::iri() converts a string representation of a UUID to an IRI
	 * (Internationalized Resource Identifier), specifically a UUID URN.
	 *
	 * For example, the NULL UUID converted to an IRI would be <code>'urn:uuid:00000000-0000-0000-0000-000000000000'</code>.
	 *
	 * @param[in] string $uuid A string representation of a UUID
	 * @returns A string containing the IRI representation of \p $uuid, or \c null if \p $uuid is not a valid UUID string.
	 * @example uuidtest.php
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
	 * @fn array parse($uuid)
	 * @brief Parse a string containing a UUID and return an array representing its value.
	 *
	 * UUID::parse() converts a string representation of a UUID to an array. The
	 * array contains the following members:
	 *
	 * - \c time_low
	 * - \c time_mid
	 * - \c time_hi_and_version
	 * - \c clock_seq_hi_and_reserved
	 * - \c clock_seq_low
	 * - \c node
	 * - \c version
	 * - \c variant
	 *
	 * The \c version member contains a UUID version number, for example UUID::RANDOM.
	 * The \c variant member specifies the UUID variant, for example UUID::DCE.
	 *
	 * @param[in] string $uuid A string representation of a UUID.
	 * @returns An array representing the supplied UUID, or \c null if an error occurs.
	 * @example uuidtest.php
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
	 * @fn string unparse($info)
	 * @brief Constructs a UUID string given an array as returned by UUID::parse()
	 *
	 * UUID::unparse() accepts an array representation of a UUID as returned by
	 * UUID::parse() and returns a string representation of the same UUID.
	 *
	 * @param[in] array $info An array representation of a UUID
	 * @returns A string representing the supplied UUID
	 * @example uuidtest.php
	 */
	public static function unparse($info)
	{
		return sprintf('%08x-%04x-%04x-%02x%02x-%12s', $info['time_low'] & 0xFFFFFFFF, $info['time_mid'], $info['time_hi_and_version'], $info['clock_seq_hi_and_reserved'], $info['clock_seq_low'], strtolower($info['node']));
	}
}
