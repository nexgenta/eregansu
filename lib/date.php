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

class EregansuDateTime extends DateTime
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

	/* Beefier parsing than with PHP's own DateTime; always uses UTC */
	public static function parse($s, $tz = null)
	{
		static $utc = null;
		
		$s = trim(strtolower($s));
		if(!strlen($s))
		{
			return;
		}
		if($utc === null)
		{
			$utc = new DateTimeZone('UTC');
		}
		if($tz === null)
		{
			$tz = $utc;
		}
		/* Standard PHP format */
		if(preg_match('!^\d{2,4}-\d{1,2}-\d{1,2}(\s+\d{1,2}:\d{1,2}(:\d{1,2})?)?$!', $s))
		{
			$dt = new EregansuDateTime($s, $tz);
			$dt->setTimezone($utc);
			return $dt;
		}
		/* Day, 99 Mon 9999 00:00[:00] (GMT|UTC|(+|-)00[:]00) */
		if(preg_match('!^[a-z]+,?\s+(\d{1,2})\s+([a-z]+)\s+(\d{4})\s+(\d{2}:\d{2}(:\d{2})?)(\s.*)?$!i', $s, $matches) && isset($matches[6]) && isset(self::$months[$matches[2]]))
		{
			$time = explode(':', $matches[4]);
			if(!isset($time[2]))
			{
				$time[2] = '00';
			}
			$s = sprintf('%04d-%02d-%02d %02d:%02d:%02d',
						 $matches[3], self::$months[$matches[2]], $matches[1], $time[0], $time[1], @$time[2]);
			$dt = new EregansuDateTime($s, $tz);
			$dt->applyTimezoneString($matches[6]);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^\d{4}-\d{2}-\d{2}t\d{2}:\d{2}(:\d{2})?[+-]\d{2}:\d{2}$/', $s))
		{
			$date = explode(' ', preg_replace('/^(\d{4}-\d{2}-\d{2})t(\d{2}:\d{2}(:\d{2}?))([+-]\d{2}:\d{2})$/', '\1 \2 \4', $s));
			$dt = new EregansuDateTime($date[0] . ' ' . $date[1], $tz);
			$dt->applyTimezoneString($date[2]);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^\d{4}-\d{2}-\d{2}t\d{2}:\d{2}(:\d{2})?[+-]\d{4}$/', $s))
		{
			$dt = explode(' ', preg_replace('/^(\d{4}-\d{2}-\d{2})t(\d{2}:\d{2}(:\d{2}?))([+-]\d{4})/', '\1 \2 \4', $s));
			$dt = new EregansuDateTime($date[0] . ' ' . $date[1], $tz);
			$dt->applyTimezoneString($date[2]);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^\d{4}-\d{2}-\d{2}t\d{2}:\d{2}(:\d{2})?z?$/', $s))
		{
			$s = preg_replace('/^(\d{4}-\d{2}-\d{2})t(\d{2}:\d{2}(:\d{2})?)z?/', '\1 \2', $s);
			$dt = new EregansuDateTime($s, $tz);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^(\d{4})(\d{2})(\d{2})t(\d{2})(\d{2})(\d{2})?z?$/', $s, $match))
		{
			$s = $match[1] . '-' . $match[2] . '-' . $match[3] . ' ' . $match[4] . ':' . $match[5] . ':' . (isset($match[6]) ? $match[6] : '00');
			$dt = new EregansuDateTime($s, $tz);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $s))
		{
			$dt = new EregansuDateTime($s . ' 00:00:00', $tz);
			$dt->setTimezone($utc);
			return $dt;
		}
		if(preg_match('/^\d{4}-\d{2}$/', $s))
		{
			$dt = new EregansuDateTime($s . '-01 00:00:00', $tz);
			$dt->setTimezone($utc);			
			return $dt;
		}
		if(preg_match('/^\d{4}$/', $s))
		{
			$dt = new EreganuDateTime($s . '-01-01 00:00:00', $tz);
			$dt->setTimezone($utc);
			return $dt;
		}
	trigger_error('Unsupported date format while parsing "' . $s . '"', E_USER_NOTICE);
	}

	protected function applyTimezoneString($tz)
	{
		$tz = strtoupper(trim($tz));
		if(!strlen($tz) || !strcmp($tz, 'utc') || !strcmp($tz, 'gmt'))
		{
			return;
		}
		if(!isset($tz['hour']) || !isset($tz['minute']))
		{
			return;
		}
		if(strpos($tz, ':') !== false)
		{
			$hm = sscanf($tz, '%d:%d');
		}
		else
		{
			$hm = array(intval(substr($tz, 0, 3)), intval(substr($tz, 3)));
		}
		if(empty($hm[0]) && empty($hm[1]))
		{
			return;
		}
		$sub = true;
		if(intval($hm[0]) < 0)
		{
			$sub = false;
			$hm[0] = intval($hm[0]) * -1;
		}
		$interval = new DateInterval('PT' . $hm[0] . 'H' . $hm[1] . 'M');
		if($sub)
		{
			$this->sub($interval);
		}
		else
		{
			$this->add($interval);
		}			
	}

	public function __toString()
	{
		return str_replace('+00:00', 'Z', $this->format(self::RFC3339));
	}

	/* Return YYYY-MM-DD */
	public function date()
	{
		return $this->format('Y-m-d');
	}
	
	/* Return HH:MM:SS */
	public function time()
	{
		return $this->format('H:i:s');
	}
}

function parse_datetime($s, $object = false, $tz = null)
{
	if(($dt = EregansuDateTime::parse($s, $tz)) === null)
	{
		return null;
	}
	if($object)
	{
		return $dt;
	}
	return $dt->getTimestamp();
	return null;
}

function format_timezone($date, $format, $zoneName)
{
	$s = strftime('%Y-%m-%d %H:%M:%S', $date);
	$dt = new DateTime($s, new DateTimeZone($zoneName));
	return $dt->format($format);
}