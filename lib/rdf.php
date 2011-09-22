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

/**
 * @year 2010-2011
 * @include uses('rdf');
 */

require_once(dirname(__FILE__) . '/date.php');
require_once(dirname(__FILE__) . '/xmlns.php');
require_once(dirname(__FILE__) . '/url.php');

/**
 * Utility methods for instantiating RDF documents.
 */
abstract class RDF extends XMLNS
{
	/* Registered ontology handlers */
	public static $ontologies = array();
	/* Registered namespaces */
	public static $namespaces = array();
	/* Preferred languages */
	public static $langs = array('en');

	/* When serialising to RDF-JSON, the list of predicates which should be serialised
	 * 'bare' (i.e., not in CURIE form)
	 */
	public static $barePredicates;
	/* When serialising to RDF-JSON, the list of predicates which should always be
	 * serialised as URIs
	 */
	public static $uriPredicates; 

	/**
	 * Create a new \class{RDFDocument} given an RDF/XML \class{DOMElement}.
	 *
	 * Construct a new \class{RDFDocument} given the root element of an RDF/XML
	 * document, such as that returned by \f{dom_import_simplexml}().
	 *
	 * @type RDFDocument
	 * @param[in] DOMElement $dom The root element of the RDF/XML document
	 * @param[in,optional] string $location The canonical source URL of the
	 *   document.
	 * @return On success, returns a new \class{RDFDocument} instance.
	 */
	public static function documentFromDOM($dom, $location = null)
	{
		$doc = new RDFDocument();
		$doc->fileURI = $location;
		if($doc->parse('application/rdf+xml', $dom))
		{
			return $doc;
		}
		return null;
	}

	/**
	 * Create a new set of triples from an RDF/XML DOMElement
	 */
	public static function tripleSetFromDOM($dom, $location = null)
	{
		$set = new RDFTripleSet();
		$set->fileURI = $location;
		$set->fromDOM($dom);
		return $set;
	}
	
	/**
	 * Create a new \class{RDFDocument} given a string containin an RDF/XML
	 * document.
	 *
	 * Parses the RDF/XML contained within \p{$document} and passes the
	 * resulting DOM tree to \m{documentFromDOM}, returning the resulting
	 * \class{RDFDocument}.
	 *
	 * @type RDFDocument
	 * @param[in] string $document The string containing the RDF/XML document.
	 * @param[in,optional] string $location The canonical source URL of the
	 *   document.
	 * @return On success, returns a new \class{RDFDocument} instance.
	 */
	public static function documentFromXMLString($document, $location = null)
	{
		$doc = new RDFDocument();
		$doc->fileURI = $location;
		if($doc->parse('application/rdf+xml', $document))
		{
			return $doc;
		}
		return null;
	}

	/* Create a new RDFTripleSet given a string containing an RDF/XML document */
	public static function tripleSetFromXMLString($string, $location = null)
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
		$dom->substituteEntities = true;
		return self::tripleSetFromDOM($dom, $location);
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
	public static function documentFromURL($location, $curl = null)
	{
		$location = strval($location);
		$ct = null;
		$doc = self::fetch($location, $ct, 'application/rdf+xml', $curl);
		if($doc === null)
		{
			return null;
		}
		if(self::isHTML($doc, $ct))
		{
			return self::documentFromHTML($doc, $location, $curl);
		}
		if(self::isXML($doc, $ct))
		{
			return self::documentFromXMLString($doc, $location, $curl);
		}
		return null;
	}

	/* Construct an RDFTripleSet from a URL */
	public static function tripleSetFromURL($location)
	{
		$location = strval($location);
		$ct = null;
		$doc = self::fetch($location, $ct, 'application/rdf+xml');
		if($doc === null)
		{
			return null;
		}
		if(self::isHTML($doc, $ct))
		{
			return self::tripleSetFromHTML($doc, $location);
		}
		if(self::isXML($doc, $ct))
		{
			return self::tripleSetFromXMLString($doc, $location);
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
	protected static function documentFromHTML($doc, $location, $curl = null)
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
			if(substr($href, -1) == '/')
			{
				$href = substr($href, 0, -1);
			}
			$href .= '.rdf';
		}
		$doc = self::fetch($href, $ct, 'application/rdf+xml', $curl);
		if(self::isXML($doc, $ct))
		{
			return self::documentFromXMLString($doc, $href);
		}
		return null;
	}
	
	/* Wrapper around Curl to fetch a resource */
	protected static function fetch($url, &$contentType, $accept = null, $curl = null)
	{
		require_once(dirname(__FILE__) . '/curl.php');
		$url = strval($url);
		if(php_sapi_name() == 'cli')
		{
			echo "RDF::fetch(): Attempting to fetch $url\n";
		}
		$contentType = null;
		if(strncmp($url, 'http:', 5) && strncmp($url, 'https:', 5))
		{
			trigger_error("RDF::fetch(): Sorry, only http URLs are supported", E_USER_WARNING);
			return null;
		}
		if(false !== ($p = strrpos($url, '#')))
		{
			$url = substr($url, 0, $p);
		}
		if($curl === null)
		{
			$curl = new CurlCache($url);
			$curl->followLocation = true;
			$curl->autoReferrer = true;
			$curl->unrestrictedAuth = true;
			$curl->httpAuth = Curl::AUTH_ANYSAFE;
		}
		$curl->returnTransfer = true;
		$curl->fetchHeaders = false;
		$headers = $curl->headers;
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
		$curl->headers['Accept'] = implode(',', $accept);
		$buf = $curl->exec();
		$info = $curl->info;
		$curl->headers = $headers;
		if(intval($info['http_code']) > 399)
		{
			echo "RDF::fetch(): HTTP status " . $info['http_code'] . "\n";
			return null;
		}
		$c = explode(';', $info['content_type']);
		$contentType = $c[0];
		if(php_sapi_name() == 'cli')
		{
			echo "RDF::fetch(): Retrieved $contentType\n";
		}
		return strval($buf);
	}

	public static function ns($uri = null, $suggestedPrefix = null, $overwrite = false)
	{
		if(!count(self::$namespaces))
		{
			self::$namespaces = array();
			self::$namespaces[RDF::rdf] = 'rdf';
			self::$namespaces[RDF::rdfs] = 'rdfs';
			self::$namespaces[RDF::owl] = 'owl';
			self::$namespaces[RDF::foaf] = 'foaf';
			self::$namespaces[RDF::skos] = 'skos';
			self::$namespaces[RDF::time] = 'time';
			self::$namespaces[RDF::dc] = 'dc';
			self::$namespaces[RDF::dcterms] = 'dct';
			self::$namespaces[RDF::rdfg] = 'rdfg';
			self::$namespaces[RDF::geo] = 'geo';
			self::$namespaces[RDF::frbr] = 'frbr';
			self::$namespaces[RDF::xhtml] = 'xhtml';
			self::$namespaces[RDF::xhv] = 'xhv';
			self::$namespaces[RDF::dcmit] = 'dcmit';
			self::$namespaces[RDF::xsd] = 'xsd';
			self::$namespaces[RDF::gn] = 'gn';
			self::$namespaces[RDF::exif] = 'exif';
		}
		if(strlen($uri))
		{
			if(strlen($suggestedPrefix) && ($overwrite || !isset(self::$namespaces[$uri])))
			{
				self::$namespaces[$uri] = $suggestedPrefix;
			}
			return isset(self::$namespaces[$uri]) ? self::$namespaces[$uri] : null;
		}
		if(strlen($suggestedPrefix))
		{
			$r = array_search($suggestedPrefix, self::$namespaces);
			if($r !== false)
			{
				return $r;
			}
		}
		return null;
	}

	public static function instanceForClass($classUri, $lname = null)
	{
		if(strlen($lname))
		{
			$ns = strval($classUri);
		}
		else
		{
			$qname = strval($classUri);
			if(false !== ($p = strrpos($qname, '#')))
			{
				$ns = substr($qname, 0, $p + 1);
				$lname = substr($qname, $p + 1);
			}
			else if(false !== ($p = strrpos($qname, ' ')))
			{
				$ns = substr($qname, 0, $p);
				$lname = substr($qname, $p + 1);
			}
			else if(false !== ($p = strrpos($qname, '/')))
			{
				$ns = substr($qname, 0, $p + 1);
				$lname = substr($qname, $p + 1);
			}
			else
			{
				return null;
			}
		}
		if(isset(self::$ontologies[$ns]))
		{
			$className = self::$ontologies[$ns];
			return call_user_func(array($className, 'rdfInstance'), $ns, $lname);
		}
		return null;
	}

	public static function barePredicates()
	{
		if(self::$barePredicates === null)
		{
			self::$barePredicates = array(
				'label' => RDF::rdfs.'label',
				'name' => RDF::foaf.'name',
				'title' => RDF::dcterms.'title',
				'description' => RDF::dcterms.'description',
				'primaryTopic' => RDF::foaf.'primaryTopic',
				'topic' => RDF::foaf.'topic',
				'page' => RDF::foaf.'page',
				'seeAlso' => RDF::rdfs.'seeAlso',
				'prev' => RDF::xhv.'prev',
				'next' => RDF::xhv.'next',
				'up' => RDF::xhv.'up',
				'first' => RDF::xhv.'first',
				'last' => RDF::xhv.'last',
				'alternate' => RDF::xhv.'alternate',
				'depiction' => RDF::foaf.'depiction',
				'sameAs' => RDF::owl.'sameAs',
				'publisher' => RDF::dcterms.'publisher',
				'created' => RDF::dcterms.'created',
				'modified' => RDF::dcterms.'modified',
				'exactMatch' => RDF::skos.'exactMatch',
				'closeMatch' => RDF::skos.'closeMatch',
				'narrowMatch' => RDF::skos.'narrowMatch',
				'broadMatch' => RDF::skos.'broadMatch',
				'noMatch' => RDF::skos.'noMatch',
				);
		}
		return self::$barePredicates;
	}

	public static function uriPredicates()
	{
		if(self::$uriPredicates === null)
		{
			self::$uriPredicates = array(
				RDF::foaf.'primaryTopic',
				RDF::foaf.'topic',
				RDF::foaf.'page',
				RDF::rdfs.'seeAlso',
				RDF::xhv.'prev',
				RDF::xhv.'next',
				RDF::xhv.'up',
				RDF::xhv.'first',
				RDF::xhv.'last',
				RDF::xhv.'alternate',
				RDF::foaf.'depiction',
				RDF::owl.'sameAs',
				RDF::dcterms.'publisher',
				RDF::skos.'exactMatch',
				RDF::skos.'closeMatch',
				RDF::skos.'narrowMatch',
				RDF::skos.'broadMatch',
				RDF::skos.'noMatch',
				);
		}
		return self::$uriPredicates;
	}

}

if(!defined('EREGANSU_RDF_IMPLEMENTATION'))
{	
	if(function_exists('librdf_new_world'))
	{
		define('EREGANSU_RDF_IMPLEMENTATION', 'redland');
	}
	else
	{
		define('EREGANSU_RDF_IMPLEMENTATION', 'phprdf');
	}
}

require_once(dirname(__FILE__) . '/' . EREGANSU_RDF_IMPLEMENTATION . '.php');

class RDFInstance extends RDFInstanceBase
{
		/* Return a URI for a QName (used by all(), first(), etc. to translate predicate names) */
	protected function translateQName($qn)
	{
		if(!strcasecmp($qn, 'subject')) return RDF::rdf . 'about';

		$s = explode(':', $qn, 2);
		if(count($s) == 2)
		{
			switch($s[0])
			{
			case 'http':
			case 'https':
			case 'urn':
			case 'tag':
			case 'info':
			case 'file':
				return $qn;
			case 'xml':
				return RDF::xml . ' ' . $s[1];
			default:
				$base = RDF::ns(null, $s[0]);
				if(strlen($base))
				{
					return $base . $s[1];
				}			
			}
		}
		return $qn;
	}
	/* Equivalent to ->all($key, false)->lang($langs, $fallbackFirst) */
	public function lang($key, $langs = null, $fallbackFirst = true)
	{
		return $this->all($key, false)->lang($langs, $fallbackFirst);
	}

	public function title($langs = null, $fallbackFirst = true)
	{
		return $this->lang(array(RDF::skos.'prefLabel', RDF::gn.'name', RDF::foaf.'name', RDF::rdfs.'label', RDF::dcterms.'title', RDF::dc.'title'), $langs, $fallbackFirst);
	}

	public function description($langs = null, $fallbackFirst = true)
	{
		return $this->lang(
			array(
				'http://purl.org/ontology/po/medium_synopsis',
				RDF::rdfs . 'comment',
				'http://purl.org/ontology/po/short_synopsis',
				'http://purl.org/ontology/po/long_synopsis',
				RDF::dcterms . 'description',
				'http://dbpedia.org/ontology/abstract',
				RDF::dc . 'description',
				), $langs, $fallbackFirst);
	}
	
	public function shortDesc($langs = null, $fallbackFirst = true)
	{
		return $this->lang(
			array(
				'http://purl.org/ontology/po/short_synopsis',
				), $langs, $fallbackFirst);
	}

	public function mediumDesc($langs = null, $fallbackFirst = true)
	{
		return $this->lang(
			array(
				'http://purl.org/ontology/po/medium_synopsis',
				RDF::rdfs . 'comment',
				), $langs, $fallbackFirst);
	}

	public function longDesc($langs = null, $fallbackFirst = true)
	{
		return $this->lang(
			array(
				'http://purl.org/ontology/po/long_synopsis',
				RDF::dcterms . 'description',
				'http://dbpedia.org/ontology/abstract',
				RDF::dc . 'description',
				), $langs, $fallbackFirst);
	}

	/* Implemented in descendent classes; maps RDF predicate/object
	 * pairs associated with this instance to traditional OOP
	 * domain-specific properties. Invoked automatically after
	 * deserialisation.
	 */
	public function transform()
	{
	}
}

class RDFDateTime extends RDFComplexLiteral
{
	public function __construct($when = null, $world = null)
	{
		if($when !== null)
		{
			parent::__construct('http://www.w3.org/2001/XMLSchema#dateTime', $when, null, $world);
		}
	}
	
	protected function setValue($value)
	{
		if(!is_resource($value))
		{
			$value = strftime('%Y-%m-%dT%H:%M:%SZ', parse_datetime($value));
		}
		parent::setValue($value);
	}
}

class RDFString extends RDFComplexLiteral
{
	public function __construct($value, $lang = null, $world = null)
	{
		parent::__construct(null, $value, $lang, $world);
	}
}


