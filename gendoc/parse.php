<?php

/* Generate API documentation from PHP sources
 *
 * Copyright 2011 Mo McRoberts
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

require_once(dirname(__FILE__) . '/tokenizer.php');

class Parser extends Tokenizer
{
	const S_BARE = 0;
	const S_ECHO = 1;
	const S_CODE = 2;

	const NS_IDENT = 0;
	const NS_IMPLEMENTS = 1;
	const NS_EXTENDS = 2;
	const NS_PARAMTYPE = 3;
	const NS_PARAMNAME = 4;
	const NS_PARAMEQ = 5;
	const NS_PARAMVAL = 6;
	const NS_PROPEQ = 7;
	const NS_STRING = 8;

	protected $state;
	protected $lastComment;
	protected $next;
	protected $stack = array();
	protected $modifiers = array();

	public $module = null;
	public $constants = array();
	public $classes = array();
	public $interfaces = array();
	public $functions = array();

	public static function tokenizeFile($path, $className = null)
	{
		if($className === null)
		{
			$className = 'Parser';
		}
		return parent::tokenizeFile($path, $className);
	}

	public static function tokenize($string, $className = null)
	{
		if($className === null)
		{
			$className = 'Parser';
		}
		return parent::tokenize($string, $className);
	}
	
	protected function __construct($tokens)
	{
		parent::__construct($tokens);
		$this->parse();
	}	

	public function cascadeAttributes($global = null)
	{
		if(!is_array($this->module))
		{
			$this->module = array();
		}
		if(!is_array($global))
		{
			$global = array();
		}
		unset($global['ignore']);
		$this->module = array_merge($global, $this->module);
		$mcleaned = $this->clean($this->module);
		$lists = array('methods', 'properties', 'constants');
		foreach($this->classes as $k => $info)
		{
			if(!isset($info['doc']))
			{
				$info['doc'] = array();
			}
			$info['doc'] = array_merge($mcleaned, $info['doc']);
			foreach($lists as $l)
			{
				if(!isset($info[$l]) || !is_array($info[$l]))
				{
					continue;
				}
				foreach($info[$l] as $mk => $method)
				{
					$ccleaned = $this->clean($info['doc']);
					if(!isset($method['doc']))
					{
						$method['doc'] = array();
					}
					$method['doc'] = array_merge($ccleaned, $method['doc']);
					$info[$l][$mk] = $method;
				}
			}
			$this->classes[$k] = $info;
		}
	}

	protected function clean($doc)
	{
		if(!is_array($doc))
		{
			$doc = array();
		}
		unset($doc['brief']);
		unset($doc['desc']);
		unset($doc['note']);
		unset($doc['todo']);
		unset($doc['see']);
		return $doc;
	}

	public function parse()
	{
//		print_r($this->tokens);
//		echo "Parsing\n";
		$this->state = self::S_BARE;
		foreach($this->tokens as $index => $token)
		{
			if(is_array($token))
			{
				$token[3] = token_name($token[0]);
//				print_r($token);
			}
			else
			{
//				echo "[Token=$token]\n";
			}
			switch($this->state)
			{
			case self::S_BARE:				
				if(is_array($token))
				{
					if($token[0] == T_OPEN_TAG)
					{
						$this->state = self::S_CODE;
					}
					else if($token[0] == T_OPEN_TAG_WITH_ECHO)
					{
						$this->state = self::S_ECHO;
					}
				}
				break;
			case self::S_ECHO:
				if(is_array($token) && $token[0] == T_CLOSE_TAG)
				{
					$this->state = self::S_BARE;
				}
				break;
			case self::S_CODE:
				if(is_array($token))
				{
					switch($token[0])
					{
					case T_CLOSE_TAG:
						$this->state = self::S_BARE;
						break;
					case T_DOC_COMMENT:
						if(!isset($this->current) && isset($this->lastComment) && !isset($this->module))
						{
							$this->module = $this->parseDoc($this->lastComment);
						}							
						$this->lastComment = $token[1];
						break;
					case T_PUBLIC:
					case T_PROTECTED:
					case T_PRIVATE:
					case T_ABSTRACT:
					case T_STATIC:
						$this->modifiers[] = $token[1];
						break;
					case T_CLASS:
					case T_INTERFACE:
					case T_FUNCTION:
						$this->next = array(
							'parent' =>& $this->current,
							'type' => $token[1],
							'ident' => null,
							'modifiers' => $this->modifiers,
							'extends' => array(),
							'implements' => array(),
							'params' => array(),
							'state' => self::NS_IDENT,
							'doc' => $this->parseDoc($this->lastComment),
							'nextParam' => null,
							);
						$this->modifiers = array();
						$this->lastComment = null;
						break;
					case T_IMPLEMENTS:
						if(isset($this->next))
						{
							$this->next['state'] = self::NS_IMPLEMENTS;
						}
						break;
					case T_EXTENDS:
						if(isset($this->next))
						{
							$this->next['state'] = self::NS_EXTENDS;
						}
						break;
					case T_STRING:
						if(isset($this->next) && isset($this->next['state']))
						{
							switch($this->next['state'])
							{
							case self::NS_IDENT:
								$this->next['ident'] = $token[1];
								break;
							case self::NS_IMPLEMENTS:
								$this->next['implements'][] = $token[1];
								break;
							case self::NS_EXTENDS:
								$this->next['extends'][] = $token[1];
								break;
							case self::NS_PARAMVAL:
								$this->next['nextParam']['value'] = $token;
								$this->next['state'] = self::NS_PARAMTYPE;
								break;
							}
						}
						else if(isset($this->next) && $this->next['type'] == 'constant')
						{
							if(isset($this->next['ident']))
							{
								$this->next['value'] = $token[1];
							}
						}
						else
						{
							if(!isset($this->current) && !isset($this->next))
							{
								/* This may be a function definition, or
								 * a function call.
								 */
								if($token[1] == 'define')
								{									
									$this->next = array(
										'parent' =>& $this->current,
										'type' => 'constant',
										'ident' => null,
										'value' => null,
										'doc' => $this->parseDoc($this->lastComment),
										);
								}
								else
								{
									if($this->lastComment !== null && !isset($this->module))
									{
										$this->module = $this->parseDoc($this->lastComment);
									}
								}
								$this->modifiers = array();
								$this->lastComment = null;
							}
						}
						break;
					case T_VARIABLE:
						if(isset($this->next))
						{
							if($this->next['state'] = self::NS_PARAMTYPE || $this->next['state'] == self::NS_PARAMNAME)
							{
								$this->next['nextParam']['ident'] = $token[1];
								$this->next['state'] = self::NS_PARAMEQ;
							}
						}
						else if(isset($this->current))
						{
							if($this->current['type'] == 'class')
							{
								/* Define a property */
								$this->next = array(
									'type' => 'property',
									'modifiers' => $this->modifiers,
									'parent' =>& $this->parent,
									'ident' => $token[1],
									'value' => null,
									'state' => self::NS_PROPEQ,
									'doc' => $this->parseDoc($this->lastComment),
									);
								$this->lastComment = null;
								$this->modifiers = array();
							}
						}
						break;
					case T_CONSTANT_ENCAPSED_STRING:
					case T_LNUMBER:
						if(isset($this->next) && $this->next['type'] == 'constant')
						{
							if(isset($this->next['ident']))
							{
								$this->next['value'] = $token[1];
							}
							else
							{
								$this->next['ident'] = $token[1];
							}
						}
						break;
					default:
//						echo "Skipping a " . token_name($token[0]) . " (" . $token[1] . ")\n";
					}
				}
				else
				{
					if($token === '{')
					{
//						echo "Pushing:\n";
						$k = count($this->stack);
						if($this->next === null)
						{
							$this->next = array('type' => 'block');
						}
						unset($this->next['state']);
						unset($this->next['nextParam']);
						$this->add($this->next);
						$this->stack[] = $this->next;
						$this->current =& $this->stack[$k];
						$this->next = null;
						$this->modifiers = array();
						$this->lastComment = null;
					}
					else if($token === '}')
					{
//						echo "Popping:\n";
//						print_r($this->current);
						array_pop($this->stack);
						if(count($this->stack))
						{
							$k = count($this->stack) - 1;
							$this->current =& $this->stack[$k];
						}
						else
						{
							$dummy = null;
							$this->current =& $dummy;
						}
						$this->next = null;
						$this->modifiers = array();
						$this->lastComment = null;
					}
					else if($token == ';')
					{
						if(isset($this->next))
						{
							$this->add($this->next);
						}
						/* Abstract function defn, or a const/property def */
						$this->next = null;
						$this->modifiers = array();
						$this->lastComment = null;
					}
					else if($token == '(')
					{
						if(isset($this->next) && $this->next['type'] == 'function')
						{
							$this->next['state'] = self::NS_PARAMTYPE;
							$this->next['nextParam'] = array('type' => null, 'ident' => null, 'value' => null);
						}
					}
					else if($token == '=')
					{
						if(isset($this->next))
						{
							if($this->next['state'] == self::NS_PARAMEQ)
							{
								$this->next['state'] = self::NS_PARAMVAL;
							}
						}
					}
					else if($token == ',' || $token == ')')
					{
						if(isset($this->next) && isset($this->next['state']))
						{
							if($this->next['state'] == self::NS_PARAMEQ || $this->next['state'] == self::NS_PARAMTYPE)
							{
								if(isset($this->next['nextParam']['ident']))
								{
									$this->next['params'][] = $this->next['nextParam'];
//									print_r($this->next['nextParam']);
								}
								$this->next['state'] = self::NS_PARAMTYPE;
								$this->next['nextParam'] = array('type' => null, 'ident' => null, 'value' => null);
							}
						}
					}
					else
					{
//						echo "Skipping a literal: " . $token . "\n";
					}
				}
				break;
			}
		}
	}


	/**
	 * Parse a documentation comment
	 */
	protected function parseDoc($string)
	{
		if(!strlen($string))
		{
			return null;
		}
		$doc = array(
			'brief' => null,
			'desc' => null,
			);
		$lines = explode("\n", $string);
		$last = count($lines) - 1;
		$prev = 'brief';
		$s = $pb = false;
		foreach($lines as $c => $l)
		{
			$l = trim($l);
			if($c === 0 && !strncmp($l, '/*', 2))
			{				
				$l = trim(substr($l, 2));
			}
			if($c === $last && substr($l, -2) == '*/')
			{
				$l = trim(substr($l, 0, -2));
			}
			$l = trim(preg_replace('!^\**!', '', $l));
			$l = trim(preg_replace('!\**$!', '', $l));
			if(!strlen($l))
			{
				if($s || $pb)
				{
					if($prev != 'desc')
					{
						$prev = 'desc';
						continue;
					}
				}
				$pb = true;
			}
			else
			{
				$pb = false;
			}
			$s = true;
			if(strlen($l) && $l[0] == '@')
			{
				$m = array();
				preg_match('/@([a-z]+)(\[([^\]]+)\]+)?\s+(.*)$/', $l, $m);
				if(!isset($m[1]))
				{
					$prev = substr($l, 1);
					$doc[$prev] = true;
				}
				else
				{
					if(isset($doc['param']))
					{
						$this->fixParam($doc);
					}
					if(isset($m[3]) && strlen($m[3]))
					{
						$doc[$m[1]] = array($m[3], $m[4]);
					}
					else
					{
						$doc[$m[1]] = $m[4];
					}
					$prev = $m[1];
				}
			}
			else
			{
				if($doc[$prev] === true)
				{
					$doc[$prev] = '';
				}
				if(is_array($doc[$prev]))
				{
					$doc[$prev][1] = trim($doc[$prev][1] . "\n" . $l);
				}
				else
				{
					$doc[$prev] = trim($doc[$prev] . "\n" . $l);
				}
			}
		}
		if(isset($doc['param']))
		{
			$this->fixParam($doc);
		}
		return $doc;
	}

	protected function fixParam(&$doc)
	{
		if(!isset($doc['param']))
		{
			return;
		}
		$param = array('direction' => null, 'type' => null, 'ident' => null, 'desc' => null);
		if(is_array($doc['param']))
		{
			$param['direction'] = $doc['param'][0];
			$doc['param'] = $doc['param'][1];
		}
		$p = str_replace('  ', ' ', str_replace("\t", ' ', $doc['param']));
		$p = explode(' ', $p, 3);
		if(isset($p[2]))
		{
			$param['type'] = $p[0];
			$param['ident'] = $p[1];
			$param['desc'] = $p[2];
		}
		if(strlen($param['ident']))
		{
			$doc['params'][$param['ident']] = $param;
		}
		unset($doc['param']);
	}

	protected function add($thing)
	{
		$x = $thing;
		unset($x['parent']);
//		echo "Adding " . @$x['ident'] . ' (' . $x['type'] . ")\n";
		switch($thing['type'])
		{
		case 'class':
			$x['properties'] = array();
			$x['methods'] = array();
			$x['constants'] = array();
			$this->classes[$thing['ident']] = $x;
			break;
		case 'interface':
			$x['methods'] = array();
			$this->classes[$thing['ident']] = $x;
			break;
		case 'function':
			if(isset($thing['parent']))
			{
				if($thing['parent']['type'] == 'class' || $thing['parent']['type'] == 'interface')
				{
					$this->classes[$thing['parent']['ident']]['methods'][$thing['ident']] = $x;
				}
			}
			else
			{
				$this->functions[$thing['ident']] = $x;
			}
			break;
		case 'property':
			if(isset($thing['parent']))
			{
				if($thing['parent']['type'] == 'class' || $thing['parent']['type'] == 'interface')
				{
					$this->classes[$thing['parent']['ident']]['properties'][$thing['ident']] = $x;
				}
			}			
			break;
		case 'constant':
			if(!isset($thing['parent']) && isset($thing['ident']))
			{
				$this->constants[$thing['ident']] = $x;
			}
			break;
		case 'block':
			/* Do nothing */
			break;
		default:
			echo "Not adding a " . $thing['type'] . "\n";
		}
		
	}
}

