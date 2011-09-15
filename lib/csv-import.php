<?php

/* Copyright 2005-2011 Mo McRoberts.
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
 * @package EregansuLib Eregansu Core Library
 * @year 2005-2011
 * @include uses('csv-import');
 * @since Available in Eregansu 1.0 and later.
 */

/**
 * Import data from a CSV file
 *
 * The \class{CSVImport} class provides the ability to import data from
 * CSV files.
 *
 * @synopsis $importer = new CSVImport('/path/to/file.csv');
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

	/**
	 * Initialise a \class{CSVImport} instance.
	 *
	 * @task Reading CSV files
	 * @param[in] string $filename The path to the CSV file to read. If
	 *   \p{$filename} ends in \x{.gz}, the file will be assumed to be
	 *   gzipped and decompressed automatically as it is read.
	 */
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

	/**
	 * @internal
	 */
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

	/**
	 * Read the list of field names from a CSV file.
	 *
	 * @task Reading CSV files
	 * @type void
	 * @param[in,optional] int $skipRows The Number of rows to skip before the header row.
	 */
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

	/**
	 * Specify an explicit column-to-field mapping.
	 *
	 * @task Reading CSV files
	 * @type void
	 * @param[in] array $list An indexed array of field names
	 */
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

	/**
	 * Move the file pointer back to the beginning of the file.
	 *
	 * @task Reading CSV files
	 * @type void
	 */
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
	
	/**
	 * Read a row from the CSV file without mapping columns to fields.
	 *
	 * @task Reading CSV files
	 * @type array
	 * @return An indexed array of values read from the file, or \c{null} if
	 *   the end of file is reached.
	 */
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

	/**
	 * Read a row from the CSV file.
	 *
	 * If a column-to-field mapping has either been provided or has been
	 * read from a header row in the source file, the returned array will
	 * be associative, otherwise it will be numerically-indexed.
	 *
	 * @task Reading CSV files
	 * @type array
	 * @return An array of values read from the file, or \c{null} if
	 *   the end of file is reached.
	 */
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
