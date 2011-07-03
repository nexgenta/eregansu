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

require_once(dirname(__FILE__) . '/url.php');

interface ISearchEngine
{
	public function __construct($uri);
	public function query($args);
}

interface ISearchIndexer
{
	public function __construct($uri);
	public function begin();
	public function indexDocument($identifier, $text, $attributes = null);
	public function commit();
}
	
abstract class SearchEngine implements ISearchEngine
{
	public $info;

	public static function connect($uri)
	{
		if(!is_object($uri))
		{
			$uri = new URL($uri);
		}
		switch($uri->scheme)
		{
		case 'http':
		case 'https':
			$class = 'GenericWebSearch';
			break;
		case 'dbplite':
			require_once(dirname(__FILE__) . '/search/dbpedialite.php');
			$class = 'DbpediaLiteSearch';
			break;
		case 'xapian+file':
			require_once(dirname(__FILE__) . '/search/xapiansearch.php');
			$class = 'XapianSearch';
			break;
		default:
			trigger_error('Unsupported search engine scheme "' . $uri->scheme . '"', E_USER_ERROR);
			return null;
		}
		$inst = new $class($uri);
		return $inst;
	}

	public function __construct($uri)
	{
		$this->info = $uri;
	}
}

abstract class SearchIndexer implements ISearchIndexer
{
	public $info;

	public static function connect($uri)
	{
		if(!is_object($uri))
		{
			$uri = new URL($uri);
		}
		switch($uri->scheme)
		{
		case 'xapian+file':
			require_once(dirname(__FILE__) . '/search/xapiansearch.php');
			$class = 'XapianIndexer';
			break;
		default:
			trigger_error('Unsupported search indexer scheme "' . $uri->scheme . '"', E_USER_ERROR);
			return null;
		}
		$inst = new $class($uri);
		return $inst;
	}

	public function __construct($uri)
	{
		$this->info = $uri;
	}
}

class GenericWebSearch extends SearchEngine
{
	public $useCache = true;

	protected $uri;
	protected $userAgent;
	protected $accept;
	protected $acceptLanguages;

	public function __construct($uri)
	{
		uses('curl');
		parent::__construct($uri);
		if(!isset($uri->query))
		{
			$uri->query = 'q=%s';
		}
		else if(strpos($uri->query, '%s') === false)
		{
			$uri->query .= '&q=%s';
		}
		$this->uri = strval($uri);
		$this->userAgent = 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27 ' . get_class($this);
		$this->accept = array('application/json', 'text/javascript');
		$this->acceptLanguages = array();
	}

	public function query($args)
	{
		if(is_array($args))
		{
			$args = $args['text'];
		}
		$uri = str_replace('%s', urlencode($args), $this->uri);		
		$curl = $this->curl($uri);
		$buf = $curl->exec();
		return $this->interpretResult($buf, $curl);
	}
	
	protected function interpretResult($buffer, $curl)
	{
		if(substr($buffer, 0, 1) != '{' && substr($buffer, 0, 1) != '[')
		{
			return null;
		}
		return json_decode($buffer, true);
	}
	
	protected function curl($uri)
	{
		if(defined('CACHE_DIR') && $this->useCache)
		{
			$c = new CurlCache($uri);
		}
		else
		{
			$c = new Curl($uri);
		}
		$c->returnTransfer = true;
		$c->followLocation = true;
		$c->headers = $this->headers();
		return $c;
	}
	
	protected function headers()
	{
		$a = array(
			'User-Agent: ' . $this->userAgent,
			'Accept: ' . implode(',', $this->accept),
			);
		if(count($this->acceptLanguages))
		{
			$a[] = 'Accept-Language: ' . implode(', ', $this->acceptLanguages);
		}
		return $a;
	}		
}
