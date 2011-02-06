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
	const rdfg = 'http://www.w3.org/2004/03/trix/rdfg-1/';

	const frbr = 'http://purl.org/vocab/frbr/core#';
	const mo = 'http://purl.org/ontology/mo/';
	const theatre = 'http://purl.org/theatre#';
	const participation = 'http://purl.org/vocab/participation/schema#';

	/* Registered ontology handlers */
	public static $ontologies = array();

	/* Create a new RDFDocument given an RDF/XML DOMElement */
	public static function documentFromDOM($dom, $location = null)
	{
		$doc = new RDFDocument();
		$doc->fileURI = $location;
		$doc->fromDOM($dom);
		return $doc;
	}
	
	/* Create a new RDFDocument given a string containing an RDF/XML document */
	public static function documentFromXMLString($string, $location = null)
	{
		$xml = @simplexml_load_string($string);
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

	/* Construct an RDFDocument from an on-disk file */
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

	/* Construct an RDFDocument from a URL */
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


	/* Attempt to determine whether a resource is XML */
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

	/* Attempt to determine whether a document is HTML */
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
			if(stripos($x, '<html') !== false || stripos($x, '<!DOCTYPE html') !== false)
			{
				return true;
			}
		}
	}

	/* Attempt to construct an RDFDocument instance given an HTML document
	 * (no RDFa parsing -- yet)
	 */
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
	
	/* Wrapper around Curl to fetch a resource */
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

/**
 * An RDF document
 */

class RDFDocument
{
	protected $subjects = array();
	protected $keySubjects = array();
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
		$this->namespaces[RDF::rdfg] = 'rdfg';
	}

	/* Promote a subject to the root of the document; in RDF/XML this
	 * means that it will appear as a child of the rdf:RDF element.
	 * Behaviour with other serialisations may vary.
	 */
	public function promote($subject)
	{
		if($subject instanceof RDFInstance)
		{
			$subject = $subject->subject();
		}
		$subject = strval($subject);
		if(!in_array($subject, $this->keySubjects))
		{
			$this->keySubjects[] = $subject;
		}
	}

	/* Locate an RDFInstance for a given subject. If $create is
	 * true, a new instance will be created (optionally a $type).
	 * Callers should explicitly invoke promote() if required.
	 */
	public function subject($uri, $type = null, $create = true)
	{
		if($uri instanceof RDFInstance)
		{
			$uri = $uri->subject();
		}
		$uri = strval($uri);
		if(isset($this->subjects[$uri]))
		{
			return $this->subjects[$uri];
		}
		foreach($this->subjects as $g)
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
		$this->subjects[$uri] = new RDFInstance($uri, $type);
		return $this->subjects[$uri];
	}

	/* Merge all of the assertions in $subject with those already in the
	 * document.
	 */
	public function merge($subject)
	{
		if($subject instanceof RDFInstance)
		{
			$uri = strval($subject->subject());
		}
		else
		{
			$uri = strval($subject);
		}
		if(($s = $this->subject($uri, null, false)))
		{
			$s->merge($subject);
			return $s;
		}
		if($subject instanceof RDFInstance)
		{
			if(strlen($uri))
			{
				$this->subjects[$uri] = $subject;
				$subject->refcount++;
				return $subject;
			}
			$this->subjects[] = $subject;
			$subject->refcount++;
			return $subject;
		}
		return null;
	}

	/* Add an RDFInstance to a document at the top level. As merge(), but
	 * always invokes promote() on the result.
	 */
	public function add(RDFInstance $subject)
	{
		if(($inst = $this->merge($subject)))
		{
			$this->promote($inst);
		}
		return $inst;
	}

	/* Completely replace all assertions about a subject in the
	 * document with a new instance.
	 */
	public function replace(RDFInstance $graph, $addIfNotFound = true)
	{		
		$uri = strval($graph->subject());
		if(!strlen($uri))
		{
			if($addIfNotFound)
			{
				$this->subjects[] = $graph;
				$graph->refcount++;
				return $graph;
			}
			return null;
		}
		foreach($this->subjects as $k => $g)
		{
			if(isset($g->{RDF::rdf . 'about'}[0]) && !strcmp($g->{RDF::rdf . 'about'}[0], $uri))
			{
				$graph->refcount = $this->subjects[$k]->refcount;
				$this->subjects[$k] = $graph;
				return $graph;
			}
			if(isset($g->{RDF::rdf . 'ID'}[0]) && !strcmp($g->{RDF::rdf . 'ID'}[0], $uri))
			{
				$graph->refcount = $this->subjects[$k]->refcount;
				$this->subjects[$k] = $graph;
				return $graph;
			}
		}
		if($addIfNotFound)
		{
			$this->subjects[$uri] = $graph;
			$graph->refcount++;
			return $graph;
		}
		return null;
	}

	/* Return the RDFInstance which is either explicitly or implicitly the
	 * primary topic of this document.
	 */
	public function primaryTopic()
	{
		if(isset($this->primaryTopic))
		{
			return $this->subject($this->primaryTopic, null, false);
		}
		$top = $file = null;
		if(isset($this->fileURI))
		{
			$top = $file = $this->subject($this->fileURI, null, false);
			if(!isset($top->{RDF::foaf . 'primaryTopic'}))
			{
				$top = null;
			}
		}
		if(!$top)
		{
			foreach($this->subjects as $g)
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
			foreach($this->subjects as $g)
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
			if($top->{RDF::foaf . 'primaryTopic'}[0] instanceof RDFInstance)
			{
				return $top->{RDF::foaf . 'primaryTopic'}[0];
			}
			$uri = strval($top->{RDF::foaf . 'primaryTopic'}[0]);
			$g = $this->subject($uri, null, false);
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

	/* Explicitly register a namespace with a given prefix */
	public function namespace($uri, $suggestedPrefix)
	{
		if(!isset($this->namespaces[$uri]))
		{
			$this->namespaces[$uri] = $suggestedPrefix;
		}
		return $this->namespaces[$uri];
	}

	/* Given a URI, generate a prefix:short form name */
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
	
	/* Serialise a document as Turtle */
	public function asTurtle()
	{
		$turtle = array();
		foreach($this->subjects as $g)
		{
			if($g->refcount < 2) continue;
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

	/* Serialise a document as RDF/XML */
	public function asXML()
	{
		$xml = array();
		foreach($this->subjects as $g)
		{
			$found = false;
			if(!$g->refcount || $g->refcount > 2)
			{
				$found = true;
			}
			else				
			{
				$uris = $g->subjects();
				foreach($uris as $u)
				{
					if(in_array(strval($u), $this->keySubjects))
					{
						$found = true;
						break;
					}
				}
			}
			if(!$found)
			{
				break;
			}
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
		return implode("\n", $xml);
	}
	
    /* Serialise a document as JSON */
	public function asJSON()
	{
		$array = array();
		foreach($this->subjects as $subj)
		{
			if($subj->refcount < 2) continue;
			$array[] = $subj->asArray();
		}
		return str_replace('\/', '/', json_encode($array));
	}

	/* Import sets of triples from an (RDF/XML) DOMElement instance */
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
				$g = new RDFInstance();
			}
			$g->fromDOM($node, $this);
			$g->refcount++;
			$g = $this->merge($g);
			$this->promote($g);
			$g->transform();			
		}
	}

	public function __get($name)
	{
		if($name == 'subjects')
		{
			return $this->subjects;
		}
		return $this->subject($name, null, false);
	}
}

/* A set of objects for a given predicate on a subject; returned by
 * $subject->all($predicate)
 */
class RDFSet implements Countable
{
	protected $values = array();
	
	public function __construct($values = null)
	{
		if($values === null) return;
		if(is_array($values))
		{
			$this->values = $values;
		}
		else
		{
			$this->values[] = $values;
		}
	}

	/* Return a simple human-readable representation of the property values */
	public function __toString()
	{
		return $this->join(', ');
	}
	
	/* Return the first value in the set */
	public function first()
	{
		if(count($this->values))
		{
			return $this->values[0];
		}
		return null;
	}
	
	/* Return a string joining the values with the given string */
	public function join($by)
	{
		if(count($this->values))
		{
			return implode($by, $this->values);
		}
	}

	/* Return the number of values held in this set; can be
	 * called as count($set) instead of $set->count().
	 */
	public function count()
	{
		return count($this->values);
	}
	
	/* Return the value matching the specified language. If $lang
	 * is an array, it specifies a list of languages in order of
	 * preference. if $fallbackFirst is true, return the first
	 * value instead of null if no language match could be found.
	 * $langs may be an array of languages, or a comma- or space-
	 * separated list in a string.
	 */
	public function lang($langs, $fallbackFirst = false)
	{
		if(!is_array($langs))
		{
			$langs = explode(',', str_replace(' ', ',', $lang));
		}
		foreach($langs as $lang)
		{
			$lang = trim($lang);
			if(!strlen($lang)) continue;
			foreach($this->values as $value)
			{
				if($value instanceof RDFComplexLiteral && isset(
					   $value->{XMLNS::xml . ' lang'}) && $value->{XMLNS::xml . ' lang'}[0] == $lang)
				{
					return strval($value);
				}
			}
		}
		foreach($this->values as $value)
		{
			if(is_string($value) || ($value instanceof RDFComplexLiteral && !isset($value->{XMLNS::xml . ' lang'})))
			{
				return strval($value);
			}
		}
		if($fallbackFirst)
		{
			foreach($this->values as $value)
			{
				return strval($value);
			}
		}
		return null;
	}
}

/* An RDF instance: an object representing a subject, where predicates are
 * properties, and objects are property values. Every property is a stringified
 * URI, and its native value is an indexed array.
 */
class RDFInstance
{
	public $refcount = 0;

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

	/* An instance's "value" is the URI  of its subject */
	public function __toString()
	{
		return strval($this->subject());
	}

	/* If this instance is a $type, return true. If $type is an instance,
	 * we compare our rdf:type against its subject, allowing, for example:
	 *
	 * $class = $doc['http://purl.org/example#Class'];
	 * if($thing->isA($class)) ...
	 */
	public function isA($type)
	{
		if($type instanceof RDFInstance)
		{
			$type = $type->subject();
		}
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

	/* Merge the assertions in $source into this instance. */
	public function merge(RDFInstance $source)
	{
		$this->refcount += $source->refcount;
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
		return $this;
	}

	/* Return the first value for the given predicate */
	public function first($key)
	{
		if(isset($this->{$key}))
		{
			return $this->{$key}[0];
		}
		return null;
	}

	/* Return the values of a given predicate */
	public function all($key, $nullOnEmpty = false)
	{
		if(isset($this->{$key}))
		{
			return new RDFSet($this->{$key});
		}
		if($nullOnEmpty)
		{
			return null;
		}
		return new RDFSet();
	}

	/* Return the first URI this instance claims to have
	 * as a subject.
	 */
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


	/* Return the set of URIs this instance has as subjects */
	public function subjects()
	{
		$subjects = array();
		if(isset($this->{RDF::rdf . 'about'}))
		{
			foreach($this->{RDF::rdf . 'about'} as $u)
			{
				$subjects[] = $u;
			}
		}
		if(isset($this->{RDF::rdf . 'ID'}))
		{
			foreach($this->{RDF::rdf . 'ID'} as $u)
			{
				$subjects[] = $u;
			}
		}
		return $subjects;
	}

	/* Implemented in descendent classes; maps RDF predicate/object
	 * pairs associated with this instance to traditional OOP
	 * domain-specific properties. Invoked automatically after
	 * deserialisation.
	 */
	public function transform()
	{
	}

	/* Serialise this instance as Turtle */
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
			if(strpos($name, ':') === false) continue;
			if(!is_array($values)) continue;
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

	/* Transform this instance into a native array which can itself be
	 * serialised as JSON to result in RDF/JSON.
	 */
	public function asArray()
	{
		$array = array();
		$props = get_object_vars($this);
		foreach($props as $name => $values)
		{
			if(strpos($name, ':') === false) continue;
			if(!is_array($values)) continue;
			$array[$name] = array();
			foreach($values as $v)
			{
				if($v instanceof RDFComplexLiteral)
				{
					$val = array('type' => 'literal', 'value' => $v->value);
					if(isset($v->{RDF::rdf . 'type'}[0]))
					{
						$val['type'] = $v->{RDF::rdf . 'type'}[0];
					}
					if(isset($v->{XMLNS::xml . ' lang'}[0]))
					{
						$val['lang'] = $v->{XMLNS::xml . ' lang'}[0];
					}
					$array[$name][] = $val;
				}
				else if($v instanceof RDFInstance)
				{
					$array[$name][] = array('type' => 'node', 'value' => $v->asArray());
				}
				else if($v instanceof RDFURI)
				{
					$array[$name][] = array('type' => 'uri', 'value' => strval($v));
				}
				else
				{
					$array[$name][] = array('type' => 'literal', 'value' => strval($v));
				}
			}
		}
		return $array;
	}

	/* Transform this instance as a string or array of strings which represent
	 * the instance as RDF/XML.
	 */
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
			if(strpos($name, ':') === false) continue;
			if($name == RDF::rdf . 'about')
			{
				$values = $about;
			}
			else if($name == RDF::rdf . 'type')
			{
				$values = $types;
			}
			if(!is_array($values) || !count($values))
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
				else if($v instanceof RDFInstance)
				{
					if($v->refcount > 1)
					{
						$rdf[] = '<' . $pname . ' rdf:resource="' . _e($v->subject()) . '" />';
					}
					else
					{
						$val = $v->asXML($doc);
						if(is_array($val))
						{
							$val = implode("\n", $val);
						}
						$rdf[] = $val;
					}
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

	/* Deserialise this instance from an RDF/XML DOMElement  */
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
					for($gnode = $node->firstChild; $gnode; $gnode = $gnode->nextSibling)
					{
						if(!($gnode instanceof DOMElement))
						{
							continue;
						}
						if(isset(RDF::$ontologies[$gnode->namespaceURI]))
						{
							$v = call_user_func(array(RDF::$ontologies[$gnode->namespaceURI], 'rdfInstance'), $gnode->namespaceURI, $gnode->localName);
						}
						if(!$v)
						{
							$v = new RDFInstance();
						}
						$v->fromDOM($gnode, $doc);
						$v = $doc->merge($v);
						$v->transform();
						$this->{XMLNS::fqname($node)}[] = $v;
					}
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
					$v = new RDFInstance();
					$v->fromDOM($node, $doc);
					$v = $doc->merge($v);
					$v->transform();
				}
				$this->{XMLNS::fqname($node)}[] = $v;
			}
		}
	}
}

class RDFComplexLiteral
{
	public $value;

	public static function literal($type = null, $value = null)
	{
		if(!strcmp($type, 'http://www.w3.org/2001/XMLSchema#dateTime'))
		{
			return new RDFDatetime($value);
		}
		return new RDFComplexLiteral($type, $value);
	}

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
