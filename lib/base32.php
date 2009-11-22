<?php

/* Copyright 2009 Mo McRoberts.
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

abstract class Base32
{
	protected static $alphabet = array(
		'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k',
		'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v',
		'w', 'x',
	);
	protected static $ralphabet;
	
	public static function encode($input)
	{
		$output = '';
		do
		{
			$v = $input % 32;
			$input = floor($input / 32);
			$output = self::$alphabet[$v] . $output;
		}
		while($input);
		return $output;
	}
	
	public static function decode($input)
	{
		if(!self::$ralphabet)
		{
			self::$ralphabet = array_flip(self::$alphabet);
		}
		$output = 0;
		$l = strlen($input);
		for($n = 0; $n < $l; $n++)
		{
			$c = $input[$n];
			echo "c = $c, l = $n, output = $output\n";
			$output *= 32;
			if(isset(self::$ralphabet[$c]))
			{
				$output += self::$ralphabet[$c];
			}
			else
			{
				return false;
			}
		}
		echo "c = $c, l = $l, output = $output\n";
		return $output;
	}
}
