<?php

require_once(dirname(__FILE__) . '/../lib/rdf.php');
require_once(dirname(__FILE__) . '/store.php');

/* RDFStore extends Store to store RDF graphs using the JSON encoding
 * described at http://n2.talis.com/wiki/RDF_JSON_Specification
 */

class RDFStore extends Store
{
	protected $storableClass = 'RDFStoredObject';
}

class RDFStoredObject extends RDFInstance
{
	protected static $models = array();

	public static function objectForData($data, $model = null, $className = null)
	{
		if(!strlen($className))
		{
			$className = 'RDFStoredObject';
		}
		if(!isset(self::$models[$className]))
		{
			self::$models[$className] = $model;
		}
		return new $className($data);
	}

	public function __construct($data)
	{
		parent::__construct();
		if(!is_arrayish($data))
		{
			throw new Exception(gettype($data) . ' passed to TroveObject::__construct(), array expected');
		}
		foreach($data as $k => $v)
		{
			if(!strcmp($k, 'refcount')) continue;
			if(is_array($v))
			{
				foreach($v as $pk => $s)
				{
					if(is_array($s) && isset($s['type']))
					{
						switch($s['type'])
						{
						case 'uri':
							$v[$pk] = new RDFURI($s['value']);
							break;
						case 'literal':
							if(isset($s['lang']) || isset($s['datatype']))
							{
								$l = RDFComplexLiteral::literal(isset($s['datatype']) ? $s['datatype'] : null, $s['value']);
								if(isset($s['lang']))
								{
									$l[RDF::xml . ' lang'] = $s['lang'];
								}
								$v[$pk] = $l;
							}
							else
							{
								$v[$pk] = $s['value'];
							}
							break;
						case 'bnode':
							if(!strncmp($s['value'], '_:', 2))
							{
								$s['value'] = '#' . substr($s['value'], 2);
							}
							$v[$pk] = new RDFURI($s['value']);
							break;
						default:
//							echo '<pre>'; print_r($data); echo '</pre>';
//							throw new Exception('unhandled RDF/JSON type ' . $s['type']);
						}
					}
				}
			}
			$this->$k = $v;
		}
	}	
}
