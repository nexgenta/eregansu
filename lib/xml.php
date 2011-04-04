<?php

/* Copyright 2010 Mo McRoberts.
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

/* A simple wrapper around the libxml2 parsing API */

class XMLParser
{
	protected static $options = array(
		'caseFolding' => XML_OPTION_CASE_FOLDING,
		'skipTagStart' => XML_OPTION_SKIP_TAGSTART,
		'skipWhite' => XML_OPTION_SKIP_WHITE,
		'targetEncoding' => XML_OPTION_TARGET_ENCODING,
		);

	protected $parser;
	public $debug;

	public function __construct($encoding = 'UTF-8')
	{
		$this->parser = xml_parser_create_ns($encoding, ' ');
		xml_set_object($this->parser, $this);
		xml_set_character_data_handler($this->parser, 'xmlCData');
		xml_set_default_handler($this->parser, 'xmlDefault');
		xml_set_element_handler($this->parser, 'xmlElementStart', 'xmlElementEnd');
		xml_set_processing_instruction_handler($this->parser, 'xmlPI');
		xml_set_start_namespace_decl_handler($this->parser, 'xmlNamespaceStart');
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
	}   

	public function __get($name)
	{
		return $this->option($name);
	}

	public function __set($name, $value)
	{
		$this->setOption($name, $value);
	}

	public function parse($data, $isFinal = false)
	{
		xml_parse($this->parser, $data, $isFinal);
	}
	
	public function errorCode()
	{
		return xml_get_error_code($this->parser);
	}

	public function byteIndex()
	{
		return xml_get_current_byte_index($this->parser);
	}
	
	public function column()
	{
		return xml_get_current_column_number($this->parser);
	}

	public function line()
	{
		return xml_get_current_line_number($this->parser);
	}

	public static function errorString($code)
	{
		return xml_error_string($code);
	}
	
	public function option($option)
	{
		if(isset(self::$options[$option]))
		{
			$option = self::$options[$option];
		}
		else if(in_array($option, self::$options))
		{
			/* okay */
		}
		else
		{
			trigger_error('unrecognised option ' . $option . ' in XMLParser::option()', E_USER_WARNING);
		}
		return xml_parser_get_option($this->parser, $option);
	}
	
	public function setOption($option, $value)
	{
		if(isset(self::$options[$option]))
		{
			$option = self::$options[$option];
		}
		else if(in_array($option, self::$options))
		{
			/* okay */
		}
		else
		{
			trigger_error('unrecognised option ' . $option . ' in XMLParser::setOption()', E_USER_WARNING);
		}
		return xml_parser_set_option($this->parser, $option, $value);
	}

	protected function xmlDefault($parser, $data)
	{
		if($this->debug)
		{
			echo "[default|$data]\n";
		}
	}

	protected function xmlCData($parser, $data)
	{
		if($this->debug)
		{
			echo "[cdata|$data]\n";
		}
	}
	
	protected function xmlElementStart($parser, $name, $attribs)
	{
		if($this->debug)
		{
			$kv = array();
			foreach($attribs as $k => $v)
			{
				$kv[] = $k . '=' . $v;
			}
			echo "[+element|$name|" . implode('|', $kv) . "]\n";
		}
	}

	protected function xmlElementEnd($parser, $name)
	{
		if($this->debug)
		{
			echo "[-element|$name]\n";
		}
	}
	
	protected function xmlPI($parser, $target, $data)
	{
		if($this->debug)
		{
			echo "[pi|$target|$data]\n";
		}
	}

	protected function xmlNamespaceStart($parser, $prefix, $uri)
	{
		if($this->debug)
		{
			echo "[+ns|$prefix|$uri]\n";
		}
	}

}
