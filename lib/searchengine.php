<?php

/* Copyright 2011-2012 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/uri.php');

URI::register('http', 'SearchEngine', array('class' => 'GenericWebSearch'));
URI::register('https', 'SearchEngine', array('class' => 'GenericWebSearch'));
URI::register('dbplite', 'SearchEngine', array('file' => dirname(__FILE__) . '/search/dbpedialite.php', 'class' => 'DbpediaLiteSearch'));
URI::register('xapian+file', 'SearchEngine', array('file' => dirname(__FILE__) . '/search/xapiansearch.php', 'class' => 'XapianSearch'));
URI::register('xapian+file', 'SearchIndexer', array('file' => dirname(__FILE__) . '/search/xapiansearch.php', 'class' => 'XapianSearch'));

interface ISearchEngine
{
	public function query($args);
}

interface ISearchIndexer
{
	public function begin();
	public function indexDocument($identifier, $text = null, $attributes = null);
	public function commit();
}

interface IIndexable
{
	public function indexIdentifier();
	public function indexBody();
	public function indexAttributes();
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
		$inst = URI::handlerForScheme($uri->scheme, 'SearchEngine', false, $uri);
		if(!is_object($inst))
		{
			throw new DBException(0, 'Unsupported search engine connection scheme "' . $uri->scheme . '"', null);
		}
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
		$inst = URI::handlerForScheme($uri->scheme, 'SearchIndexer', false, $uri);
		if(!is_object($inst))
		{
			throw new DBException(0, 'Unsupported search indexer connection scheme "' . $uri->scheme . '"', null);
		}
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
		if(!($uri instanceof URL))
		{
			$uri = new URL($uri);
		}
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
