<?php

/* Eregansu: CSV Import
 *
 * Copyright 2005-2011 Mo McRoberts.
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

class CSVImport
{
	protected $file;
	protected $fields;
	
	public $charset = 'UTF-8';
	public $lowerCaseFields = false;
	public $replacements = array();
	
	protected $mode = null;
	protected $prevLine = array();
	protected $prevField = null;
	protected $prevQ = false;
	
	public function __construct($filename)
	{
		if(substr($filename, -3) == '.gz')
		{
			$this->mode = 'gzip';
			$this->file = gzopen($filename, 'rb');
		}
		else
		{
			$this->file = fopen($filename, "rb");
		}
		if(!$this->file)
		{
			throw new Exception('Failed to open CSV file ' . $filename);
		}
		$this->fields = array();
	}
	
	public function __destruct()
	{
		switch($this->mode)
		{
			case 'gzip':
				gzclose($this->file);
				break;
			default:
				fclose($this->file);
		}
	}
	
	public function readFields($skipRows = 0)
	{
		$this->rewind();
		while($skipRows > 0)
		{
			if($this->getLine() == null) return false;
			$skipRows--;
		}
		$this->fields = $this->getLine();
		if($this->lowerCaseFields)
		{
			foreach($this->fields as $k => $v)
			{
				$this->fields[$k] = strtolower($v);
			}
		}
	}

	public function setFields($list)
	{
		if($this->lowerCaseFields)
		{
			$this->fields = array();
			foreach($list as $k => $f)
			{
				$this->fields[$k] = $f;
			}
		}
		else
		{
			$this->fields = $list;
		}
	}

	public function rewind()
	{
		switch($this->mode)
		{
			case 'gzip':
				gzrewind($this->file);
				break;
			default:
				rewind($this->file);
		}
	}
	
	public function rowFlat()
	{
		return $this->getLine();
	}
	
	protected function getLine()
	{
		$line = $this->prevLine;
		$q = $this->prevQ;
		$field = $this->prevField;
		while(!feof($this->file))
		{
			while(!feof($this->file))
			{
				switch($this->mode)
				{
				case 'gzip':
					$buf = gzgets($this->file);
					break;
				default:
					$buf = fgets($this->file);
				}
				$buf = trim($buf);
				if(!strlen($buf))
				{
					if(feof($this->file))
					{
						$this->prevLine = array();
						$this->prevField = '';
						$this->prevQ = false;
						return $line;
					}
					break;
				}
				if(count($this->replacements))
				{
					$buf = str_replace(array_keys($this->replacements), array_values($this->replacements), $buf);
				}
				if($this->charset != 'UTF-8')
				{
					$buf = iconv($this->charset, 'UTF-8//IGNORE', $buf);
				}
				for($c = 0; $c < strlen($buf); $c++)
				{
					if($buf[$c] == '"' && $q)
					{
						/* "" within a quoted section indicates literal quotes */
						if($c + 1 < strlen($buf) && $buf[$c + 1] == '"')
						{
							$field .= '"';
							$c++;
							continue;
						}
						$q = false;
						continue;
					}
					if($buf[$c] == '"')
					{
						$q = true;
						continue;
					}
					if(!$q && $buf[$c] == ',')
					{
						$line[] = $field;
						$field = '';
						continue;
					}
					$field .= $buf[$c];
				}
				if($q)
				{
					$field .= "\n";
					continue;
				}
				$line[] = $field;
				break;
			}
			if($q)
			{
				$this->prevLine = $line;
				$this->prevField = $field . "\n";
				$this->prevQ = $q;
				return true;
			}
			$this->prevLine = array();
			$this->prevField = '';
			$this->prevQ = false;
			if(count($line))
			{
				return $line;
			}
		}
	}
	
	public function row()
	{
		do
		{
			if(($line = $this->getLine()) == null)
			{
				return null;
			}
		}
		while($line === true);
		foreach(array_keys($line) as $k)
		{
			if(isset($this->fields[$k]))
			{
				$line[$this->fields[$k]] = $line[$k];
				unset($line[$k]);
			}
		}
		return $line;
	}
}
