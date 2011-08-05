<?php

/* Eregansu: ASN.1 Utilities
 *
 * Copyright 2011 Mo McRoberts.
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
 * @package EregansuLib Eregansu Core Library
 * @year 2011
 * @include uses('asn1');
 * @since Available in Eregansu 1.0 and later. 
 */

/**
 * ASN.1 decoding support.
 *
 * The ASN1 class provides the ability to decode ASN.1 structures which
 * conform to the Basic Encoding Rules (BER).
 *
 * @synopsis $structure = ASN1::decodeBER($blob);
 */

abstract class ASN1
{
	/**
	 * @internal
	 */
	public static $types = array(
		0 => 'EOC',
		1 => 'BOOLEAN',
		2 => 'INTEGER',
		3 => 'BIT-STRING',
		4 => 'OCTET-STRING',
		5 => 'NULL',
		6 => 'OBJECT',
		7 => 'ObjectDescriptor',
		8 => 'EXTERNAL',
		9 => 'REAL',
		10 => 'ENUMERATED',
		11 => 'EMBEDDED-PDV',
		12 => 'UTF8String',
		13 => 'RELATIVE-OID',
		16 => 'SEQUENCE',
		17 => 'SET',
		18 => 'NumericString',
		19 => 'PrintableString',
		20 => 'T61String',
		21 => 'VideotexString',
		22 => 'IA5String',
		23 => 'UTCTime',
		24 => 'GeneralizedTime',
		25 => 'GraphicString',
		26 => 'VisibleString',
		27 => 'GeneralString',
		28 => 'UniversalString',
		29 => 'CHARACTER-STRING',
		30 => 'BMPString',
		);
	
	/**
	 * Decode a BER-encoded ASN.1 structure.
	 *
	 * \m{decodeBER} provides limited support for decoding a binary ASN.1
	 * structure which was encoded using the Basic Encoding Rules (BER), such
	 * as X.509 certificate data.
	 *
	 * Each member of the returned array is itself an associative array with
	 * the following fields: \x{$type}, \x{$pc}, \x{$class}, \x{$len},
	 * and \x{$data} (note that \x{$data} is base-64 encoded).
	 *
	 * For entries which are sequences, the \x{$sequence} member is an
	 * indexed array of structures.
	 *
	 * For entries which are bit-strings, the \x{$value} member is set to the
	 * value of the bit-string, and the \x{$unused} member is set to the
	 * number of leading unused bits in the string.
	 *
	 * @type array
	 * @param[in] string $binary The BER-encoded ASN.1 structure
	 * @return An indexed array representing the ASN.1 structure.
	 * @task ASN.1 decoding
	 */
	public static function decodeBER($binary)
	{
		$output = array();
		while(true)
		{
			@$typelen = unpack('Ctype/Clen', $binary);
			if(!is_array($typelen)) break;
			$binary = substr($binary, 2);
			$type = $typelen['type'] & 31;
			if(!$type) break;
			$pc = ($typelen['type'] & 32) >> 5;
			$class = ($typelen['type'] & 192) >> 6;
			$len = $typelen['len'];
			if($len & 128)
			{
				$lenbytes = $len & ~128;
				$leni = unpack('C' . $lenbytes . 'len', $binary);
				$binary = substr($binary, $lenbytes);
				$len = 0;
				foreach($leni as $byte)
				{
					$len *= 256;
					$len += $byte;
				}
			}
			if($len > strlen($binary)) break;
			$data = substr($binary, 0, $len);
			if(isset(self::$types[$type]))
			{
				$type = self::$types[$type];
			}
			$entry = array(
				'type' => $type,
				'pc' => $pc,
				'class' => $class,
				'len' => $len,
				'data' => base64_encode($data),
				);
			if($type == 'SEQUENCE')
			{
				$entry['sequence'] = self::decodeBER($data);
			}
			if($type == 'BIT-STRING')
			{
				$unused = unpack('Cunused', $data);
				$entry['unused'] = $unused['unused'];
				$value = substr($data, 1);
				$entry['value'] = base64_encode($value);
				$seq = self::decodeBER($value);
				if(isset($seq[0]))
				{
					$entry['sequence'] = $seq;
				}			
			}
			$output[] = $entry;
			$binary = substr($binary, $len);
		}
		return $output;
	}
}
