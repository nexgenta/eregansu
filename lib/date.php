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
	static $months = array(
		'jan' => '01',
		'feb' => '02',
		'mar' => '03',
		'apr' => '04',
		'may' => '05',
		'jun' => '06',
		'jul' => '07',
		'aug' => '08',
		'sep' => '09',
		'oct' => '10',
		'nov' => '11',
		'dec' => '12',
		);

	$s = trim($s);
	if(!strlen($s))
	{
		return null;
	}
	if(strpos($s, ' ') !== false)
	{
		return strtotime($s);
	}
	/* Day, 99 Mon 9999 00:00[:00] (GMT|UTC|(+|-)00[:]00) */
	if(preg_match('!^[a-z]+,?\s+(\d{1,2})\s+([a-z]+)\s+(\d{4})\s+(\d{2}:\d{2}(:\d{2})?)(\s.*)?$!i', $s, $matches) && isset($matches[6]) && isset($months[strtolower($matches[2])]))
	{
		$tm = strftime($matches[3] . '-' . $months[strtolower($matches[2])] . '-' . $matches[1] . ' ' . $matches[4]);
		$tz = strtoupper(trim($matches[6]));
		if(!strlen($tz) || !strcmp($tz, 'UTC') || !strcmp($tz, 'GMT'))
		{
			return $tm;
		}
		if(strpos($tz, ':') !== false)
		{
			$hm = sscanf($tz, '%d:%d');
			$hm[1] = ($hm[0] < 0 ? $hm[1] * -1 : $hm[1]);
			$tm -= ($hm[0] * 3600) + ($hm[1] * 60);
		}
		else
		{
			$hm = array(intval(substr($dt[2], 0, 3)), intval(substr($dt[2], 3)));
			$hm[1] = ($hm[0] < 0 ? $hm[1] * -1 : $hm[1]);
			$tm -= ($hm[0] * 3600) + ($hm[1] * 60);
		}
		return $tm;
	}
	if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?[+-]\d{2}:\d{2}$/', $s))
	{
		$dt = explode(' ', preg_replace('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}(:\d)?{2})([+-]\d{2}:\d{2})/', '\1 \2 \4', $s));
		$tm = strtotime($dt[0] . ' ' . $dt[1]);
		$hm = sscanf($dt[2], '%d:%d');
		$hm[1] = ($hm[0] < 0 ? $hm[1] * -1 : $hm[1]);
		$tm -= ($hm[0] * 3600) + ($hm[1] * 60);
		return $tm;
	}
	if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?[+-]\d{4}$/', $s))
	{
		$dt = explode(' ', preg_replace('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}(:\d{2}))([+-]\d{4})/', '\1 \2 \4', $s));
		$tm = strtotime($dt[0] . ' ' . $dt[1]);
		$hm = array(intval(substr($dt[2], 0, 3)), intval(substr($dt[2], 3)));
		$hm[1] = ($hm[0] < 0 ? $hm[1] * -1 : $hm[1]);
		$tm -= ($hm[0] * 3600) + ($hm[1] * 60);
		return $tm;
	}
	if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?Z?$/', $s))
	{
		$s = preg_replace('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}(:\d{2})?)Z?/', '\1 \2', $s);
		return strtotime($s);
	}
	if(preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?Z?$/', $s, $match))
	{
		$s = $match[1] . '-' . $match[2] . '-' . $match[3] . ' ' . $match[4] . ':' . $match[5] . ':' . (isset($match[6]) ? $match[6] : '00');
		return strtotime($s);
	}
	if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $s))
	{
		return strtotime($s . ' 00:00:00');
	}
	if(preg_match('/^\d{4}-\d{2}$/', $s))
	{
		return strtotime($s . '-01 00:00:00');
	}
	if(preg_match('/^\d{4}$/', $s))
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