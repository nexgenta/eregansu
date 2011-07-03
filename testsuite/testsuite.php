<?php

/* Copyright 2011 Mo McRoberts.
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

class TestSuite
{
	public $phpPath = null;
	public $tests = array();
	public $logFile = 'tests.log';
	public $quiet = false;
	public $issuesUrl = null;

	public $total = 0;
	public $pass = 0;
	public $fail = 0;
	public $xpass = 0;
	public $xfail = 0;
	public $uxpass = 0;
	public $uxfail = 0;

	protected $nameLength = 0;

	public static function suiteFromXML($path, $logFile = null, $phpPath = null, $className = null)
	{
		if(!strlen($className))
		{
			$className = 'TestSuite';
		}
		$tests = array();
		$root = simplexml_load_file('tests.xml');
		if(!is_object($root)) return null;
		foreach($root->test as $t)
		{
			$attrs = $t->attributes();
			$expect = 'pass';
			$name = strval($t);
			$issue = null;
			if(isset($attrs->expect))
			{
				$expect = strval($attrs->expect);
			}
			if(isset($attrs->issue))
			{
				$issue = strval($attrs->issue);
			}
			$tests[] = array('name' => $name, 'expect' => strtolower($expect), 'issue' => $issue);
		}
		return new $className($tests, $logFile, $phpPath);
	}   	
	
	public function __construct($tests = null, $logFile = null, $phpPath = null)
	{
		if(is_array($tests))
		{
			$this->tests = $tests;
		}
		if(strlen($phpPath))
		{
			$this->phpPath = $phpPath;
		}
		if(strlen($logFile))
		{
			$this->logFile = $logFile;
		}
	}

	public function run()
	{
		$this->prologue();
		foreach($this->tests as $test)
		{
			$this->invoke($test);
		}
		$this->epilogue();
	}
	
	protected function prologue()
	{
		$this->total = 0;
		$this->pass = $this->fail = 0;
		$this->xpass = $this->xfail = 0;
		$this->uxpass = $this->uxfail = 0;
		$this->nameLength = 0;
		foreach($this->tests as $k => $test)
		{
			$i = pathinfo($test['name']);
			if(isset($i['dirname']) && strcmp($i['dirname'], '.'))
			{
				$printName = $i['dirname'] . '/';
			}
			else
			{
				$printName = '';
			}
			$printName .= $test['name'];
			if(isset($test['extension']) && strlen($i['extension']) && strcmp($i['extension'], 'php'))
			{
				$printName .= '.' . $test['extension'];
			}
			if(strlen($printName) > $this->nameLength)
			{
				$this->nameLength = strlen($printName);
			}
			$this->tests[$k]['printName'] = $printName;
		}
		if(!strlen($this->phpPath))
		{
			$s = getenv('PHP_5');
			if(defined('PHP_PATH'))
			{
				$this->phpPath = PHP_PATH;
			}
			else if(strlen($s))
			{
				$this->phpPath = $s;
			}
			else
			{
				$this->phpPath = 'php';
			}
		}
		$f = fopen($this->logFile, 'w');
		fwrite($f, "Test run beginning at " . strftime('%Y-%m-%dT%H:%M:%SZ') . "\n");
		fwrite($f, str_repeat('=', 72) . "\n");
		fclose($f);
	}

	protected function epilogue()
	{
		$f = fopen($this->logFile, 'a');
		fwrite($f, "Test run completed at " . strftime('%Y-%m-%dT%H:%M:%SZ') . "\n");
		$this->uxpass = $this->pass - $this->xpass;
		$this->uxfail = $this->fail - $this->xfail;
		$buf = "Total: " . $this->total . ' -- ';
		$buf .= $this->pass . ' passed' . ($this->uxpass ? ' (' . $this->uxpass . ' unexpected)' : '') . ', ';
		$buf .= $this->fail . ' failed' . ($this->uxfail ? ' (' . $this->uxfail . ' unexpected)' : '') . ".\n";
		fwrite($f, $buf);
		fclose($f);
		$this->write($buf);		
	}

	protected function write()
	{
		if($this->quiet)
		{
			return;
		}
		$a = func_get_args();
		echo implode(' ', $a);
	}

	protected function invoke($test)
	{
		$f = fopen($this->logFile, 'a');
		fprintf($f, "%s - Executing test: %s, expected result: %s\n", strftime('%Y-%m-%dT%H:%M:%SZ'), $test['name'], $test['expect']);
		if(strlen($test['issue']))
		{
			fprintf($f, "Issue URL: http://github.com/nexgenta/eregansu/issues/%s\n", $test['issue']);
		}
		$this->total++;
		$this->write(sprintf('%' . $this->nameLength . 's', $test['printName']) . " ... ");
		$status = 256;
		if(file_exists($test['name']))
		{
			$command = $this->phpPath . ' -f ' . escapeshellarg(realpath(dirname(__FILE__) . '/harness.php')) . ' ' . escapeshellarg($test['name']) . ' >>' . escapeshellarg($this->logFile) . ' 2>&1';
			fwrite($f, ' + ' . $command . "\n");
			fwrite($f, str_repeat('-', 72) . "\n"); 
			fclose($f);
			$result = system($command, $status);
			if($result === false)
			{
				$status = 256;
			}
			$f = fopen($this->logFile, 'a');
			if($status)
			{
				fwrite($f, str_repeat('-', 72) . "\n");
			}
			fprintf($f, "%s - Test %s completed with status %d\n", strftime('%Y-%m-%dT%H:%M:%SZ'), $test['name'], $status);			
		}
		else
		{
			fwrite($f, "Test cannot be executed because " . $test['name'] . " does not exist\n");
		}
		fwrite($f, str_repeat('=', 72) . "\n");
		fclose($f);
		if($status > 126)
		{
			$this->write("*** ERROR *** (failed to execute test)");
			$this->fail++;
		}
		else if($status == 0)
		{
			$this->pass++;
			if($test['expect'] == 'pass')
			{
				$this->write("PASS");
				$this->xpass++;
			}
			else
			{
				$this->write("UXPASS");
			}
		}
		else
		{
			$this->fail++;
			if($test['expect'] == 'fail')
			{
				$this->write("XFAIL");
				$this->xfail++;
				$status = 0;
				if(strlen($test['issue']))
				{
					if(strlen($this->issuesUrl))
					{
						if(strpos($this->issuesUrl, '%s') !== false)
						{
							$issueStr = str_replace('%s', $test['issue'], $this->issuesUrl);
						}
						else
						{
							$issueStr  = $this->issuesUrl . $test['issue'];
						}
					}
					else
					{
						$issueStr = 'See issue ' . $test['issue'];
					}
					$this->write(" -- " . $issueStr);
				}
			}
			else
			{
				$this->write("FAIL ($status)");
			}
		}
		$this->write("\n");
		return $status;
	}
	
}
