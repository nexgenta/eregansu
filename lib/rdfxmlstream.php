<?php

/* Copyright 2010-2011 Mo McRoberts.
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

require_once(dirname(__FILE__)  . '/xml.php');
require_once(dirname(__FILE__)  . '/rdf.php');

class RDFXMLStreamParser extends XMLParser
{
	protected $depth;
	protected $ns = array();
	protected $targetDepth = 2;
	protected $buf;
	protected $elStack = array();
	protected $started = array();
	public $rdfDocumentReady = null;

	public function __construct($encoding = 'UTF-8')
	{
		parent::__construct();
		$this->setOption('skipWhite', true);
		$this->depth = 0;
	}

	protected function xmlNamespaceStart($parser, $prefix, $uri)
	{
		$this->ns[$this->depth + 1][$prefix] = $uri;
	}
	
	protected function xmlElementStart($parser, $name, $attrs)
	{
		$ns = $elns = array();
		for($c = 0; $c <= $this->depth; $c++)
		{
			if(isset($this->ns[$c]))
			{
				foreach($this->ns[$c] as $prefix => $uri)
				{
					$ns[$prefix] = $uri;
				}
			}
		}
		if($this->depth < $this->targetDepth)
		{
			$elns = $ns;
			$this->buf = '';
		}		
		if($this->depth >= $this->targetDepth)
		{
			if(!$this->started[$this->depth])
			{
				$this->buf .= '>';
				$this->started[$this->depth] = true;
			}
		}
		$this->depth++;		
		if($this->depth >= $this->targetDepth)
		{
			if(isset($this->ns[$this->depth]))
			{
				foreach($this->ns[$this->depth] as $prefix => $uri)
				{
					$ns[$prefix] = $elns[$prefix] = $uri;
				}
			}
			$x = explode(' ', $name);
			if(count($x) > 1)
			{
				$prefix = array_search($x[0], $ns);
				$this->buf .= '<' . $prefix . ':' . $x[1];
				$this->elStack[$this->depth] = $prefix . ':' . $x[1];
			}
			else
			{
				$this->buf .= '<' . $name;
				$this->elStack[$this->depth] = $name;
			}
			foreach($elns as $prefix => $uri)
			{
				$this->buf .= ' xmlns:' . $prefix . '="' . _e($uri) . '"';
			}
			foreach($attrs as $aname => $avalue)
			{
				$x = explode(' ', $aname);
				if(count($x) > 1)
				{
					$prefix = array_search($x[0], $ns);
					$this->buf .= ' ' . $prefix . ':' . $x[1] . '="' . _e($avalue) . '"';
				}
				else
				{
					$this->buf .= ' ' . $aname . '="' . $avalue . '"';
				}
			}
			$this->started[$this->depth] = false;
		}
	}
	
	protected function xmlElementEnd($parser, $name)
	{
		if($this->depth >= $this->targetDepth)
		{
			if($this->started[$this->depth])
			{
				$this->buf .= '</' . $this->elStack[$this->depth] . '>';
			}
			else
			{
				$this->buf .= ' />';
			}
		}
		unset($this->ns[$this->depth]);
		unset($this->started[$this->depth]);
		$this->depth--;
		if($this->depth < $this->targetDepth && strlen($this->buf))
		{
			$this->rdfInstanceReady($this->buf);
			$this->buf = '';
		}
	}

	protected function xmlCData($parser, $data)
	{
		if($this->depth >= $this->targetDepth)
		{
			if(!$this->started[$this->depth])
			{
				$this->buf .= '>';
				$this->started[$this->depth] = true;
			}			
			$this->buf .= _e($data);
		}
	}

	protected function rdfInstanceReady($xmlDoc)
	{
		$buf = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
			'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n" .
			$xmlDoc . "\n" .
			'</rdf:RDF>';
		$doc = RDF::documentFromXMLString($buf);
		if(isset($this->rdfDocumentReady))
		{
			call_user_func($this->rdfDocumentReady, $doc);
		}
	}
}