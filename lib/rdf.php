<?php

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
		$this->namespaces['http://www.w3.org/1999/02/22-rdf-syntax-ns#'] = 'rdf';
		$this->namespaces['http://www.w3.org/2000/01/rdf-schema#'] = 'rdfs';
		$this->namespaces['http://www.w3.org/2002/07/owl#'] = 'owl';
		$this->namespaces['http://xmlns.com/foaf/0.1/'] = 'foaf';
		$this->namespaces['http://purl.org/dc/elements/1.1/'] = 'dc';
		$this->namespaces['http://purl.org/dc/terms/'] = 'dct';
		$this->namespaces['http://www.w3.org/2008/05/skos#'] = 'skos';
		$this->namespaces['http://www.w3.org/2006/time#'] = 'time';
	}

	public function graph($uri, $type = null)
	{
		if(isset($this->graphs[$uri]))
		{
			return $this->graphs[$uri];
		}
		if($type === null && !strcmp($uri, $this->fileURI))
		{
			$type = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#Description';
		}
		$this->graphs[$uri] = new RDFGraph($uri, $type);
		return $this->graphs[$uri];
	}

	public function namespace($uri, $suggestedPrefix)
	{
		if(!isset($this->namespaces[$uri]))
		{
			$this->namespaces[$uri] = $suggestedPrefix;
		}
		return $this->namespaces[$uri];
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
			if(!isset($this->namespaces[$ns]))
			{
				$this->namespaces[$ns] = 'ns' . count($this->namespaces);
			}
			$pname = $this->namespaces[$ns] . ':' . $lname;
			$this->qnames[$qname] = $pname;
		}
		return $this->qnames[$qname];
	}
}

class RDFGraph
{
	public function __construct($uri, $type = null)
	{
		$this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#about'}[] = new RDFURI($uri);
		if(strlen($type))
		{
			$this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI($type);
		}
	}

	public function asXML($doc)
	{
		if(!isset($this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}))
		{
			return null;
		}
		$types = $this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'};
		$primaryType = $doc->namespacedName(array_shift($types));
		if(isset($this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#about'}))
		{
			$about = $this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#about'};
		}
		else
		{
			$about = array();
		}
		$rdf = array();
		if(count($about))
		{
			$rdf[] = '<' . $primaryType . ' rdf:about="' . _e(array_shift($about)) . '">';
		}
		else
		{
			$rdf[] = '<' . $primaryType . '>';
		}
		$props = get_object_vars($this);
		$c = 0;
		foreach($props as $name => $values)
		{
			if($name == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#about')
			{
				$values = $about;
			}
			else if($name == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type')
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
				else if(is_object($v) && isset($v->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#dataType'}))
				{
					$type = $v->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#dataType'}[0];
					$rdf[] = '<' . $pname . ' rdf:dataType="' . _e($type) . '">' . _e($v) . '</' . $pname . '>';
				}
				else
				{
					$rdf[] = '<' . $pname . '>' . _e($v) . '</' . $pname . '>';
				}
			}
		}
		$rdf[] = '</' . $primaryType . '>';
		return $rdf;
	}
}

class RDFURI
{
	public $uri;

	public function __construct($uri)
	{
		$this->uri = $uri;
	}
	
	public function __toString()
	{
		return $this->uri;
	}
}

class RDFComplexLiteral
{
	public $value;

	public function __construct($type = null, $value = null)
	{
		if($type !== null)
		{
			$this->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#dataType'}[] = $type;
		}
		$this->value = $value;
	}
	
	public function __toString()
	{
		return $this->value;
	}
}

class RDFDateTime extends RDFComplexLiteral
{
	public function __construct($when)
	{
		parent::__construct('http://www.w3.org/2001/XMLSchema#dateTime', strftime('%Y-%m-%dT%H:%M:%SZ', parse_datetime($when)));
	}
}
