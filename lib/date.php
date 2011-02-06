<?php

/* Eregansu: Date/time string parsing
 *
 * Copyright 2009 Mo McRoberts.
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