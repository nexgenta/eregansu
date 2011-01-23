<?php

/* Copyright 2010 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/date.php');
require_once(dirname(__FILE__) . '/xmlns.php');
require_once(dirname(__FILE__) . '/url.php');

abstract class RDF extends XMLNS
{
	const rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
	const rdfs = 'http://www.w3.org/2000/01/rdf-schema#';
	const owl = 'http://www.w3.org/2002/07/owl#';
	const foaf = 'http://xmlns.com/foaf/0.1/';
	const skos = 'http://www.w3.org/2008/05/skos#';
	const time = 'http://www.w3.org/2006/time#';
	
	public static $ontologies = array();

	public static function documentFromDOM($dom, $location = null)
	{
		$doc = new RDFDocument();
		$doc->fileURI = $location;
		$doc->fromDOM($dom);
		return $doc;
	}
	
	public static function documentFromXMLString($string, $location = null)
	{
		$xml = simplexml_load_string($string);
		if(!is_object($xml))
		{
			return null;
		}
		$dom = dom_import_simplexml($xml);
		if(!is_object($dom))
		{
			return null;
		}
		return self::documentFromDOM($dom, $location);
	}

	protected static function isXML($doc, $ct)
	{
		if($doc === null)
		{
			return null;
		}
		if($ct == 'application/rdf+xml' || $ct == 'text/xml' || $ct == 'application/xml')
		{
			return true;
		}
		if($ct == 'application/x-unknown' || $ct == 'application/octet-stream' || $ct == 'text/plain')
		{			
			/* Content sniffing, kill me now! */
			$x = substr($doc, 0, 1024);
			if(stripos($x, 'xmlns=') !== false || strpos($x, 'xmlns:') !== false)
			{
				return true;
			}
		}
		return false;
	}

	protected static function isHTML($doc, $ct)
	{
		if($ct == 'text/html' || $ct == 'application/xhtml+xml')
		{
			return true;
		}
		if($ct == 'application/x-unknown' || $ct == 'application/octet-stream' || $ct == 'text/plain')
		{			
			/* Content sniffing, kill me now! */
			$x = substr($doc, 0, 1024);
			if(stripos($x, '<html') !== false)
			{
				return true;
			}
		}
	}

	public static function documentFromFile($path, $location = null)
	{
		if(!strlen($location))
		{
			$location = 'file://' . realpath($path);
		}
		if(($buf = file_get_contents($path)) !== false)
		{
			return self::documentFromXMLString($buf, $location);
		}
	}

	public static function documentFromURL($location)
	{
		$ct = null;
		$doc = self::fetch($location, $ct, 'application/rdf+xml');
		if($doc === null)
		{
			return null;
		}
		if(self::isHTML($doc, $ct))
		{
			return self::documentFromHTML($doc, $location);
		}
		if(self::isXML($doc, $ct))
		{
			return self::documentFromXMLString($doc, $location);
		}
		return null;
	}
	
	protected static function documentFromHTML($doc, $location)
	{
		require_once(dirname(__FILE__) . '/../simplehtmldom/simple_html_dom.php');
		$html = new simple_html_dom();
		$html->load($doc);
		$links = array();
		foreach($html->find('link') as $link)
		{
			$l = array(
				'rel' => @$link->attr['rel'],
				'type' => @$link->attr['type'],
				'href' => @$link->attr['href'],
				);
			if(strlen($l['rel']) &&
			   strpos(' ' . $l['rel'] . ' ', ' alternate ') === false &&
			   strpos(' ' . $l['rel'] . ' ', ' meta ') === false)
			{
				continue;
			}
			if(!strcmp($l['type'], 'application/rdf+xml'))
			{
				$links['rdf'] = $l;
			}
			$links[] = $l;
		}
		if(isset($links['rdf']))
		{
			$href = new URL($links['rdf']['href'], $location);
		}
		else
		{
			$href = $location;
			if(false !== ($p = strrpos($href, '#')))
			{
				$href = substr($href, 0, $p);
			}
			if(false !== ($p = strrpos($href, '.html')))
			{
				$href = substr($href, 0, $p);
			}
			$href .= '.rdf';
		}
		$doc = self::fetch($href, $ct, 'application/rdf+xml');
		if(self::isXML($doc, $ct))
		{
			return self::documentFromXMLString($doc, $href);
		}
		return null;
	}
	
	protected static function fetch($url, &$contentType, $accept = null)
	{
		require_once(dirname(__FILE__) . '/curl.php');
		$url = strval($url);
		$contentType = null;
		if(strncmp($url, 'http:', 5))
		{
			trigger_error("RDF::fetch(): Sorry, only http URLs are supported", E_USER_ERROR);
			return null;
		}
		if(false !== ($p = strrpos($url, '#')))
		{
			$url = substr($url, 0, $p);
		}
		if(defined('CACHE_DIR'))
		{
			$curl = new CurlCache($url);
		}
		else
		{
			$curl = new Curl($url);
		}
		if(!is_array($accept))
		{
			if(strlen($accept))
			{
				$accept = array($accept);
			}
			else
			{
				$accept = array();
			}
		}
		$accept[] = '*/*';
		$curl->returnTransfer = true;
		$curl->followLocation = true;
		$curl->headers = array('Accept: ' . implode(',', $accept));
		$buf = $curl->exec();
		$info = $curl->info;
		$c = explode(';', $info['content_type']);
		$contentType = $c[0];	
		return strval($buf);
	}
}

class RDFDocument
{
	protected $graphs = array();
	protected $namespaces = array();
	protected $qnames = array();
	public $fileURI;
	public $primaryTopic;

	public function __construct($fileURI = null, $primaryTopic = null)
	{
		$this->fileURI = $fileURI;
		$this->primaryTopic = $primaryTopic;
		$this->namespaces[RDF::rdf] = 'rdf';
		$this->namespaces[RDF::rdfs] = 'rdfs';
		$this->namespaces[RDF::owl] = 'owl';
		$this->namespaces[RDF::foaf] = 'foaf';
		$this->namespaces[RDF::skos] = 'skos';
		$this->namespaces[RDF::time] = 'time';
		$this->namespaces[RDF::dc] = 'dc';
		$this->namespaces[RDF::dcterms] = 'dct';
	}

	public function graph($uri, $type = null, $create = true)
	{
		$uri = strval($uri);
		if(isset($this->graphs[$uri]))
		{
			return $this->graphs[$uri];
		}
		foreach($this->graphs as $g)
		{
			if(isset($g->{RDF::rdf . 'about'}[0]) && !strcmp($g->{RDF::rdf . 'about'}[0], $uri))
			{
				return $g;
			}
			if(isset($g->{RDF::rdf . 'ID'}[0]) && !strcmp($g->{RDF::rdf . 'ID'}[0], $uri))
			{
				return $g;
			}
		}
		if(!$create)
		{
			return null;
		}
		if($type === null && !strcmp($uri, $this->fileURI))
		{
			$type = RDF::rdf . 'Description';
		}
		$this->graphs[$uri] = new RDFGraph($uri, $type);
		return $this->graphs[$uri];
	}

	public function setGraph($graph)
	{
		$uri = null;
		if(isset($graph->{RDF::rdf . 'about'}[0]))
		{
			$uri = strval($graph->{RDF::rdf . 'about'}[0]);
		}
		if(isset($graph->{RDF::rdf . 'ID'}[0]))
		{
			$uri = strval($graph->{RDF::rdf . 'ID'}[0]);
		}
		if($uri === null)
		{
			$this->graphs[] = $graph;
			return $graph;
		}
		foreach($this->graphs as $k => $g)
		{
			if(isset($g->{RDF::rdf . 'about'}[0]) && !strcmp($g->{RDF::rdf . 'about'}[0], $uri))
			{
				$this->graphs[$k] = $graph;
				return $graph;
			}
			if(isset($g->{RDF::rdf . 'ID'}[0]) && !strcmp($g->{RDF::rdf . 'ID'}[0], $uri))
			{
				$this->graphs[$k] = $graph;
				return $graph;
			}
		}
		$this->graphs[$uri] = $graph;
		return $graph;
	}

	public function primaryTopic()
	{
		if(isset($this->primaryTopic))
		{
			return $this->graph($this->primaryTopic, null, false);
		}
		$top = $file = null;
		if(isset($this->fileURI))
		{
			$top = $file = $this->graph($this->fileURI, null, false);
			if(!isset($top->{RDF::foaf . 'primaryTopic'}))
			{
				$top = null;
			}
		}
		if(!$top)
		{
			foreach($this->graphs as $g)
			{
				if(isset($g->{RDF::rdf . 'type'}[0]) && !strcmp($g->{RDF::rdf . 'type'}[0], RDF::rdf . 'Description'))
				{
					$top = $g;
					break;
				}
			}			
		}
		if(!$top)
		{
			foreach($this->graphs as $g)
			{
				$top = $g;
				break;
			}
		}
		if(!$top)
		{
			return null;
		}
		if(isset($top->{RDF::foaf . 'primaryTopic'}[0]))
		{
			if($top->{RDF::foaf . 'primaryTopic'}[0] instanceof RDFGraph)
			{
				return $top->{RDF::foaf . 'primaryTopic'}[0];
			}
			$uri = strval($top->{RDF::foaf . 'primaryTopic'}[0]);
			$g = $this->graph($uri, null, false);
			if($g)
			{
				return $g;
			}
		}
		if($file)
		{
			return $file;
		}
		return $top;
	}

	public function namespace($uri, $suggestedPrefix)
	{
		if(!isset($this->namespaces[$uri]))
		{
			$this->namespaces[$uri] = $suggestedPrefix;
		}
		return $this->namespaces[$uri];
	}
	
	public function asTurtle()
	{
		$turtle = array();
		foreach($this->graphs as $g)
		{
			$x = $g->asTurtle($this);
			if(is_array($x))
			{
				$x = implode("\n", $x);
			}
			$turtle[] = $x . "\n";
		}
		if(count($this->namespaces))
		{
			array_unshift($turtle, '');
			foreach($this->namespaces as $ns => $prefix)
			{
				array_unshift($turtle, '@prefix ' . $prefix . ': <' . $ns . '>');
			}
		}
		return $turtle;
	}

	public function asXML()
	{
		$xml = array();
		foreach($this->graphs as $g)
		{
			$x = $g->asXML($this);
			if(is_array($x))
			{
				$x = implode("\n", $x);
			}
			$xml[] = $x . "\n";
		}
		$nslist = array();
		foreach($this->namespaces as $ns => $prefix)
		{
			$nslist[] = 'xmlns:' . $prefix . '="' . _e($ns) . '"';
		}
		array_unshift($xml, '<rdf:RDF ' . implode(' ', $nslist) . '>' . "\n");
		$xml[] = '</rdf:RDF>';
		array_unshift($xml, '<?xml version="1.0" encoding="UTF-8"?>');
					 
		return $xml;
	}

	public function namespacedName($qname)
	{
		$qname = strval($qname);
		if(!isset($this->qnames[$qname]))
		{
			if(false !== ($p = strrpos($qname, '#')))
			{
				$ns = substr($qname, 0, $p + 1);
				$lname = substr($qname, $p + 1);
			}
			else if(false !== ($p = strrpos($qname, '/')))
			{
				$ns = substr($qname, 0, $p + 1);
				$lname = substr($qname, $p + 1);
			} 
			else
			{
				return $qname;
			}			
			if(!isset($this->namespaces[$ns]))
			{
				$this->namespaces[$ns] = 'ns' . count($this->namespaces);
			}
			$pname = $this->namespaces[$ns] . ':' . $lname;
			$this->qnames[$qname] = $pname;
		}
		return $this->qnames[$qname];
	}

	public function fromDOM($root)
	{
		for($node = $root->firstChild; $node; $node = $node->nextSibling)
		{
			if(!($node instanceof DOMElement))
			{
				continue;
			}
			$g = null;
			if(isset(RDF::$ontologies[$node->namespaceURI]))
			{
				$g = call_user_func(array(RDF::$ontologies[$node->namespaceURI], 'rdfInstance'), $node->namespaceURI, $node->localName);
			}
			if(!$g)
			{
				$g = new RDFGraph();
			}
			$g->fromDOM($node, $this);
			$g->transform();
			if(null !== ($s = $g->subject()))
			{
				$s = strval($s);
				if(isset($this->graphs[$s]))
				{
					$this->graphs[$s]->mergeFromGraph($g);
				}
				else
				{
					$this->graphs[$s] = $g;
				}
			}
			else
			{
				$this->graphs[] = $g;
			}			
		}
	}

	public function __get($name)
	{
		if($name == 'graphs')
		{
			return $this->graphs;
		}
	}
}

class RDFGraph
{
	public function __construct($uri = null, $type = null)
	{
		if(strlen($uri))
		{
			$this->{RDF::rdf . 'about'}[] = new RDFURI($uri);
		}
		if(strlen($type))
		{
			$this->{RDF::rdf . 'type'}[] = new RDFURI($type);
		}
	}

	public function __toString()
	{
		if(isset($this->{RDF::rdf . 'about'}))
		{
			return strval($this->{RDF::rdf . 'about'}[0]);
		}
		return '';
	}

	public function isA($type)
	{
		if(isset($this->{RDF::rdf . 'type'}))
		{
			foreach($this->{RDF::rdf . 'type'} as $t)
			{
				if(!strcmp($t, $type))
				{
					return true;
				}
			}
		}
		return false;
	}

	public function mergeFromGraph(RDFGraph $source)
	{
		foreach($source as $prop => $values)
		{
			foreach($values as $value)
			{
				$match = false;
				if(isset($this->{$prop}))
				{
					foreach($this->{$prop} as $val)
					{
						if($val == $value)
						{
							$match = true;
							break;
						}
					}
				}
				if(!$match)
				{
					$this->{$prop}[] = $value;
				}
			}
		}
	}

	public function first($key)
	{
		if(isset($this->{$key}))
		{
			return $this->{$key}[0];
		}
		return null;
	}

	public function asTurtle($doc)
	{
		$turtle = array();
		if(isset($this->{RDF::rdf . 'about'}))
		{
			$about = $this->{RDF::rdf . 'about'};
		}
		else
		{
			$about = array();
		}
		if(count($about))
		{
			$first = array_shift($about);
			$turtle[] = '<' . $first . '>';
		}
		else
		{
			$turtle[] = '_:anonymous';
		}
		if(isset($this->{RDF::rdf . 'type'}))
		{
			$types = $this->{RDF::rdf . 'type'};
			$tlist = array();
			foreach($types as $t)
			{
				$tlist[] = $doc->namespacedName(strval($t));
			}
			$turtle[] = "\t" . 'a ' . implode(' , ', $tlist) . ' ;';
		}
		if(count($about))
		{
			$tlist = array();
			foreach($about as $u)
			{
				$list[] = '<' . $u . '>';
			}
			$turtle[] = "\t" . ' rdf:about ' . implode(' , ', $tlist) . ' ;';
		}
		$props = get_object_vars($this);
		$c = 0;
		foreach($props as $name => $values)
		{
			if(substr($name, 0, 1) == '_') continue;
			if($name == RDF::rdf . 'about')				
			{
				continue;
			}
			else if($name == RDF::rdf . 'type')
			{
				continue;
			}
			if(!count($values))
			{
				continue;
			}
			$name = $doc->namespacedName($name);
			$vlist = array();
			foreach($values as $v)
			{
				if(is_string($v) || $v instanceof RDFComplexLiteral)
				{
					$suffix = null;
					if(is_object($v))
					{
						if(isset($v->{RDF::rdf . 'datatype'}) && count($v->{RDF::rdf . 'datatype'}))
						{
							$suffix = '^^' . $doc->namespacedName($v->{RDF::rdf . 'datatype'}[0]);
						}
						else if(isset($v->{XML::xml . ' lang'}) && count($v->{XML::xml . ' lang'}))
						{
							$suffix = '@' . $v->{XML::xml . ' lang'}[0];
						}
						$v = strval($v);
					}
					if(strpos($v, "\n") !== false || strpos($v, '"') !== false)
					{
						$vlist[] = '"""' . $v . '"""' . $suffix;
					}
					else
					{
						$vlist[] = '"' . $v . '"' . $suffix;
					}
				}
				else if($v instanceof RDFURI)
				{
					$vlist[] = '<' . $v . '>';
				}
			}
			$turtle[] = "\t" . $name . ' ' . implode(" ,\n\t\t", $vlist) . ' ;';
		}
		$last = array_pop($turtle);
		$turtle[] = substr($last, 0, -1) . '.';
		return $turtle;
	}

	public function asXML($doc)
	{
		if(!isset($this->{RDF::rdf . 'type'}))
		{
			return null;
		}
		$types = $this->{RDF::rdf . 'type'};
		$primaryType = $doc->namespacedName(array_shift($types));
		if(isset($this->{RDF::rdf . 'about'}))
		{
			$about = $this->{RDF::rdf . 'about'};
		}
		else
		{
			$about = array();
		}
		$rdf = array();
		if(count($about))
		{
			$top = $primaryType . ' rdf:about="' . _e(array_shift($about)) . '"';
		}
		else
		{
			$top = $primaryType;
		}
		$rdf[] = '<' . $top . '>';
		$props = get_object_vars($this);
		$c = 0;
		foreach($props as $name => $values)
		{
			if(substr($name, 0, 1) == '_') continue;
			if($name == RDF::rdf . 'about')				
			{
				$values = $about;
			}
			else if($name == RDF::rdf . 'type')
			{
				$values = $types;
			}
			if(!count($values))
			{
				continue;
			}
			$pname = $doc->namespacedName($name);
			foreach($values as $v)
			{
				$c++;
				if($v instanceof RDFURI)
				{
					$rdf[] = '<' . $pname . ' rdf:resource="' . _e($v) . '" />';
				}
				else if($v instanceof RDFGraph)
				{
					$val = $v->asXML($doc);
					if(is_array($val))
					{
						$val = implode("\n", $val);
					}
					$rdf[] = $val;
				}
				else if(is_object($v))
				{
					$props = get_object_vars($v);
					$attrs = array();
					foreach($props as $k => $values)
					{
						if($k == 'value')
						{
							continue;
						}
						$attrs[] = $doc->namespacedName($k) . '="' . _e($values[0]) . '"';
					}
					if(!($v instanceof RDFXMLLiteral))
					{
						$v = _e($v);
					}
					$rdf[] = '<' . $pname . (count($attrs) ? ' ' . implode(' ', $attrs) : '') . '>' . $v . '</' . $pname . '>';
				}
				else
				{
					$rdf[] = '<' . $pname . '>' . _e($v) . '</' . $pname . '>';
				}
			}
		}
		if(!$c)
		{
			return '<' . $top . ' />';
		}
		$rdf[] = '</' . $primaryType . '>';
		return $rdf;
	}

	public function fromDOM($root, $doc)
	{
		$this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI(XMLNS::fqname($root));
		foreach($root->attributes as $attr)
		{
			$v = strval($attr->value);
			if($attr->namespaceURI == RDF::rdf)
			{
				if($attr->localName == 'about' || $attr->localName == 'resource')
				{
					$v = new RDFURI($v, $doc->fileURI);
				}
				else if($attr->localName == 'ID')
				{
					$v = new RDFURI('#' . $v, $doc->fileURI);
				}
			}
			$this->{XMLNS::fqname($attr)}[] = $v;
		}
		for($node = $root->firstChild; $node; $node = $node->nextSibling)
		{
			if(!($node instanceof DOMElement))
			{
				continue;
			}
			$parseType = null;
			$type = null;
			$nattr = 0;
			
			if($node->hasAttributes())
			{
				foreach($node->attributes as $attr)
				{
					if($attr->namespaceURI == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' &&
					   $attr->localName == 'datatype')
					{
						$type = $attr->value;
					}
					else if($attr->namespaceURI == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' &&
							$attr->localName == 'parseType')
					{
						$parseType = $attr->value;
						$nattr++;
					}
					else
					{
						$nattr++;
					}
				}
			}
			$parseType = strtolower($parseType);
			if($node->hasChildNodes() || $parseType == 'literal')
			{
				/* Might be a literal, a complex literal, or a graph */
				$child = $node->firstChild;
				if($parseType == 'literal' || ($child instanceof DOMCharacterData && !$child->nextSibling))
				{
					$value = $child->textContent;
					if($parseType == 'literal')
					{
						$v = new RDFXMLLiteral();
					}
					else if(strlen($type) || $nattr)
					{
						if($type == 'http://www.w3.org/2001/XMLSchema#dateTime')
						{
							$v = new RDFDateTime();
						}
						else
						{
							$v = new RDFComplexLiteral();
						}
					}
					else
					{
						$v = $value;
					}
					if(is_object($v))
					{
						$v->fromDOM($node, $doc);
					}
					$this->{XMLNS::fqname($node)}[] = $v;
				}
				else
				{
					$v = null;
					if(isset(RDF::$ontologies[$node->namespaceURI]))
					{
						$v = call_user_func(array(RDF::$ontologies[$node->namespaceURI], 'rdfInstance'), $node->namespaceURI, $node->localName);
					}
					if(!$v)
					{
						$v = new RDFGraph();
					}
					$v->fromDOM($node, $doc);
					$this->{XMLNS::fqname($node)}[] = $v;
				}
			}
			else
			{
				/* If there's only one attribute and it's rdf:resource, we
				 * can compress the whole thing to an RDFURI instance.
				 */
				$uri = null;
				foreach($node->attributes as $attr)
				{
					if($uri !== null)
					{
						$uri = null;
						break;
					}
					if($attr->namespaceURI != 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' ||
					   $attr->localName != 'resource')
					{
						break;
					}
					$uri = $attr->value;
				}
				if($uri !== null)
				{
					$v = new RDFURI($uri, $doc->fileURI);
				}
				else
				{
					$v = new RDFGraph();
					$v->fromDOM($node, $doc);
					$v->transform();
				}
				$this->{XMLNS::fqname($node)}[] = $v;
			}
		}
	}
	
	public function subject()
	{
		if(null !== ($s = $this->first(RDF::rdf . 'about')))
		{
			return $s;
		}
		if(null !== ($s = $this->first(RDF::rdf . 'ID')))
		{
			return $s;
		}
	}

	public function transform()
	{
	}
}

class RDFComplexLiteral
{
	public $value;

	protected function setValue($value)
	{
		$this->value = $value;
	}

	public function __construct($type = null, $value = null)
	{
		if($type !== null)
		{
			$this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#datatype'}[] = $type;
		}
		if($value !== null)
		{
			$this->setValue($value);
		}
	}

	public function fromDOM($node, $doc)
	{
		foreach($node->attributes as $attr)
		{
			$this->{XMLNS::fqname($attr)}[] = $attr->value;
		}
		$this->setValue($node->textContent);
	}
	
	public function __toString()
	{
		return $this->value;
	}
}

class RDFURI extends URL
{
	public function __construct($uri, $base = null)
	{
		parent::__construct($uri, $base);		
		$this->value = parent::__toString();
	}
	
	public function __toString()
	{
		return $this->value;
	}
}

class RDFXMLLiteral extends RDFComplexLiteral
{
	public function fromDOM($node, $pdoc)
	{
		parent::fromDOM($node);
		$doc = array();
		for($c = $node->firstChild; $c; $c = $c->nextSibling)
		{
			$doc[] = $node->ownerDocument->saveXML($c);
		}
		$this->value = implode('', $doc);
	}
}

class RDFDateTime extends RDFComplexLiteral
{
	public function __construct($when = null)
	{
		if($when !== null)
		{
			parent::__construct('http://www.w3.org/2001/XMLSchema#dateTime', $when);
		}
	}
	
	protected function setValue($value)
	{
		$this->value = strftime('%Y-%m-%dT%H:%M:%SZ', parse_datetime($value));
	}
}
