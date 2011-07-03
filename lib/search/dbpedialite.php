<?php

/* Copyright 2011 Mo McRoberts.
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

if(!defined('DBPEDIALITE_URI')) define('DBPEDIALITE_URI', 'http://dbpedialite.org/search.json?term=%s');

class DbpediaLiteSearch extends GenericWebSearch
{
	public function __construct($uri)
	{
		if($uri->scheme == 'dbplite')
		{
			$uri->scheme = 'http';
		}			
		if(!strlen($uri->host))
		{
			$uri = new URL(DBPEDIALITE_URI);
		}
		parent::__construct($uri);
	}

	protected function interpretResult($result, $curl)
	{
		$result = parent::interpretResult($result, $curl);
		if(!is_array($result))
		{
			return null;
		}
		$rs = array(
			'count' => count($result),
			'list' => array()
			);
		foreach($result as $r)
		{
			$entry = array(
				'title' => $r['label'],
				'uri' => $this->info->scheme . '://' . $this->info->host . (!empty($this->info->port) ? ':' . $this->info->port : '') . '/titles/' . rawurlencode($r['label']),
				);
			$rs['list'][] = $entry;
		}
		return $rs;
	}
}
		