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

require_once(dirname(__FILE__) . '/searchengine.php');

if(defined('XAPIAN_PHP_PATH'))
{
	require_once(XAPIAN_PHP_PATH);
}
else
{
	require_once('xapian.php');
}

class XapianSearch extends SearchEngine
{
	public $db;
	public $stemmer;
	public $prefixes = array();

	protected $path;

	const BOOL = 0;
	const PROBABILISTIC = 1;

	public function __construct($uri)
	{
		parent::__construct($uri);
		$this->path = $uri->path;
		$this->db = new XapianDatabase($uri->path);
		$this->stemmer = new XapianStem('english');
	}

	public function query($args)
	{
		$qp = new XapianQueryParser();
		$qp->set_stemmer($this->stemmer);
		$qp->set_database($this->db);
		$qp->set_stemming_strategy(XapianQueryParser::STEM_SOME);
		$text = null;
		$query = array();
		$offset = 0;
		$limit = 10;
		$order = null;
		if(!is_array($args))
		{
			$args = array('text' => $args);
		}
		foreach($this->prefixes as $prefix => $info)
		{
			if(is_string($info))
			{
				$qp->add_boolean_prefix($key, $info);
				$info = array('type' => self::BOOL, 'prefix' => $info);
			}
			else
			{
				if(!isset($info['type']))
				{
					$info['type'] = self::BOOL;
				}
				if($info['type'] == self::BOOL)
				{
					$qp->add_boolean_prefix($key, $info['prefix']);
				}
				else
				{
					$qp->add_prefix($key, $info['prefix'], !empty($info['exclusive']));
				}
			}
			$this->prefixes[$prefix] = $info;
		}
		foreach($args as $key => $value)
		{
			if(!strcmp($key, 'offset'))
			{
				$offset = intval($value);
				continue;
			}
			if(!strcmp($key, 'limit'))
			{
				$limit = intval($value);
				continue;
			}
			if(!strcmp($key, 'order'))
			{
				$order = $value;
				continue;
			}
			if(strpos($key, '?') !== false)
			{
				continue;
			}
			if(strcmp($key, 'text'))
			{
				if(isset($this->prefixes[$key]))
				{
					$prefix = $this->prefixes[$key]['prefix'];
					$q = '"';
				}
				else
				{
					$qp->add_boolean_prefix($key, 'X' . strtoupper($key) . ':');
					$prefix = $key . ':';
					$q = '"';
				}
			}
			else
			{
				$prefix = '';
				$q = '';
			}
			if(is_array($value))
			{
				foreach($value as $ivalue)
				{
					$query[] = $prefix . $q . $ivalue . $q;
				}
			}
			else
			{
				$query[] = $prefix . $q . $value . $q;
			}
		}
		$xq = new XapianQuery(
			$qp->parse_query(
				implode(' ', $query),
				XapianQueryParser::FLAG_PHRASE|XapianQueryParser::FLAG_BOOLEAN|XapianQueryParser::FLAG_LOVEHATE|XapianQueryParser::FLAG_WILDCARD|XapianQueryParser::FLAG_PARTIAL)
			);
		$enquire = new XapianEnquire($this->db);
		$enquire->set_query($xq);
		try
		{
			$matches = $enquire->get_mset($offset, $limit);
			$i = $matches->begin();
			$results = array(
				'offset' => $offset,
				'limit' => $limit,
				'total' => $matches->get_matches_estimated(),
				'list' => array(),
				);
			while (!$i->equals($matches->end()))
			{
				$data = json_decode($i->get_document()->get_data(), true);
				$results['list'][] = $data;
				$i->next();
			}
		}
		catch(Exception $e)
		{
			$match = 'DatabaseModifiedError:';
			if(!strcmp($e->getMessage(), $match, strlen($match)))
			{
				return $this->query($args);
			}
			throw $e;
		}
		return $results;
	}
}

class XapianIndexer extends SearchIndexer
{
	public $db;
	public $indexer;
	public $stemmer;
	
	protected $path;

	public function __construct($uri)
	{
		parent::__construct($uri);
		$this->db = null;
		$this->indexer = new XapianTermGenerator();
		$this->stemmer = new XapianStem('english');
		$this->indexer->set_stemmer($this->stemmer);
		$this->path = $uri->path;
	}

	public function begin()
	{
		if($this->db !== null)
		{
			trigger_error('Attempt to invoke XapianIndexer::begin() multiple times without intervening XapianIndexer::commit()', E_USER_NOTICE);
			return;
		}
		$this->db = new XapianWritableDatabase($this->path, Xapian::DB_CREATE_OR_OPEN);
		$this->chmod();
	}

	public function commit()
	{
		if($this->db === null)
		{
			trigger_error('Attempt to invoke XapianIndexer::commit() without first calling XapianIndexer::begin()', E_USER_NOTICE);
			return;
		}
		$this->db->flush();
		$this->db = null;
		$this->chmod();
	}

	protected function chmod()
	{
		$files = array('flintlock', 'iamchert', 'position', 'postlist', 'record', 'termlist');
		$suf = array('', '.DB', '.baseA', '.baseB');
		chmod($this->info->path, 0755);
		foreach($files as $f)
		{
			foreach($suf as $s)
			{
				if(file_exists($this->info->path . '/' . $f . $s))
				{
					chmod($this->info->path . '/' . $f . $s, 0644);
				}
			}
		}
	}		

	public function indexDocument($identifier, $fullText, $attributes = null)
	{
		if($this->db === null)
		{
			$this->begin();
		}
		$doc = new XapianDocument();
		if(is_array($attributes))
		{
			foreach($attributes as $key => $value)
			{
				if(is_array($value))
				{
					foreach($value as $ivalue)
					{
						$doc->add_term('X' . strtoupper($key) . ':' . $ivalue);
					}
				}
				else
				{
					$doc->add_term('X' . strtoupper($key) . ':' . $value);
				}
			}
			$doc->set_data(json_encode($attributes));
		}
		else if(is_object($attributes))
		{
			$doc->set_data(json_encode($attributes));
		}
		else if($attributes !== null)
		{
			$doc->set_data($attributes);
		}
		$doc->add_term('Q' . $identifier);
		$this->indexer->set_document($doc);
		if(is_array($fullText))
		{
			$fullText = implode("\n", $fullText);
		}
		$this->indexer->index_text($fullText);
		$this->db->delete_document('Q' . $identifier);		
		$this->db->add_document($doc);
	}
}
