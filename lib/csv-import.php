<?php

/* Eregansu: CSV Import
 *
 * Copyright 2005-2011 Mo McRoberts.
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
		while(true)
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
		if(!count($line))
		{
			return null;
		}
		return $line;
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
