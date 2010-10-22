<?php

/* Eregansu: Date/time string parsing
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

function parse_datetime($s)
{
	$s = trim($s);
	if(!strlen($s))
	{
		return null;
	}
	if(strpos($s, ' ') !== false)
	{
		return strtotime($s);
	}
	if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $s))
	{
		$dt = explode(' ', preg_replace('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})([+-]\d{2}:\d{2})/', '\1 \2 \3', $s));
		$tm = strtotime($dt[0] . ' ' . $dt[1]);
		$hm = sscanf($dt[2], '%d:%d');
		$hm[1] = ($hm[0] < 0 ? $hm[1] * -1 : $hm[1]);
		$tm -= ($hm[0] * 3600) + ($hm[1] * 60);
		return $tm;
	}
	else if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?$/', $s))
	{
		$s = preg_replace('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z?/', '\1 \2', $s);
		return strtotime($s);
	}
	else if(preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?Z?$/', $s, $match))
	{
		$s = $match[1] . '-' . $match[2] . '-' . $match[3] . ' ' . $match[4] . ':' . $match[5] . ':' . (isset($match[6]) ? $match[6] : '00');
		return strtotime($s);
	}
	else if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $s))
	{
		return strtotime($s . ' 00:00:00');
	}
	else if(preg_match('/^\d{4}-\d{2}$/', $s))
	{
		return strtotime($s . '-01 00:00:00');
	}
	else if(preg_match('/^\d{4}$/', $s))
	{
		return strtotime($s . '-01-01 00:00:00');
	}
	trigger_error('Unsupported date format while parsing "' . $s . '"', E_USER_NOTICE);
	return null;
}

function format_timezone($date, $format, $zoneName)
{
	$s = strftime('%Y-%m-%d %H:%M:%S', $date);
	$dt = new DateTime($s, new DateTimeZone($zoneName));
	return $dt->format($format);
}