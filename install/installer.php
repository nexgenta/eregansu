<?php

/* Copyright 2010 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/module.php');

class Installer
{
	public $appname;
	public $appconfig;
	public $appconfigCreated;
	public $config;
	public $configCreated;
	public $instname;
	public $configuredModules = 0;
	
	public $installerName = 'Eregansu Install';
	public $defaultAppName = 'default';
	public $defaultInstanceName = null;
	
	public $relPlatformPath;
	public $relModulesPath;
	
	public function __construct()
	{
		if(!strlen($this->defaultInstanceName))
		{
			$hostname = explode('.', php_uname('n'));		
			$this->defaultInstanceName = $hostname[0];
		}
	}
	
	public function run()
	{
		$this->init();
		$this->checkConfigRoot();
		$this->checkAppName();
		$this->checkAppConfig();
		$this->checkAppConfigLink();
		$this->checkInstanceName();
		$this->checkConfig();
		$this->checkConfigLink();
		$this->checkIndex();
		$this->checkEregansuLink();
		$this->checkHtAccess();
		$this->checkApp();
		$this->checkTemplates();
		$this->scanModules();
		$this->postamble();
		
		/* Once this class has been finished, we should return true to indicate success */
	
		return true;
	}
	
	protected function init()
	{
		umask(022);
		echo $this->installerName . "\n";
		echo str_repeat('=', strlen($this->installerName)) . "\n\n";
		
		echo "This script will generate an initial set of configuration files for your\n";
		echo "application based upon the answers you give below.\n\n";

		echo "Files will be stored in the following locations:\n\n";

		echo " - Instance root:       " . INSTANCE_ROOT . "\n";
		echo " - Application modules: " . MODULES_ROOT . "\n";
		echo " - Configuration files: " . CONFIG_ROOT . "\n";
		echo " - Eregansu platform:   " . PLATFORM_ROOT . "\n";
		
		if(!strncmp(INSTANCE_ROOT, PLATFORM_ROOT, strlen(INSTANCE_ROOT)))
		{
			$this->relPlatformPath = substr(PLATFORM_ROOT, strlen(INSTANCE_ROOT));
		}
		else
		{
			$this->relPlatformPath = PLATFORM_ROOT;
		}
		if(!strncmp(INSTANCE_ROOT, MODULES_ROOT, strlen(INSTANCE_ROOT)))
		{
			$this->relModulesPath = substr(MODULES_ROOT, strlen(INSTANCE_ROOT));
		}
		else
		{
			$this->relModulesPath = MODULES_ROOT;
		}
		echo "\n";
	}
	
	protected function checkConfigRoot()
	{
		if(file_exists(CONFIG_ROOT))
		{
			echo "--> " . CONFIG_ROOT . " already exists.\n\n";
		}
		else
		{
			echo "--> " . CONFIG_ROOT . " does not exist, creating.\n\n";
			if(!mkdir(CONFIG_ROOT)) exit(1);
			chmod(CONFIG_ROOT, 0755);
		}
	}
	
	protected function checkAppName()
	{
		echo "\nYou need to specify a short name for your application. This name will be used\n";
		echo "in the filename of your application configuration file. For example, if you\n";
		echo "set the short name to be 'myapp', the application configuration file will be\n";
		echo "created as appconfig.myapp.php. A symbolic link will be created from this file\n";
		echo "to appconfig.php.\n\n";
		
		do
		{
			echo "Please enter a name consisting only of letters, numbers and dashes, 1-16 characters\n";
			$this->appname = $this->prompt('Application name', $this->defaultAppName);
			if(strlen($this->appname) < 1 || strlen($this->appname) > 16) continue;
			if(!preg_match('/^[A-Za-z0-9-]+$/', $this->appname)) continue;
			break;
		}
		while(true);
		echo "\n";
	}
	
	protected function checkAppConfig()
	{
		$this->appconfig = CONFIG_ROOT . 'appconfig.' . $this->appname . '.php';				
		if(file_exists($this->appconfig))
		{
			echo "The file appconfig." . $this->appname . ".php already exists. Would you like to delete it\n";
			echo "and create a new one?\n\n";
			do
			{
				$ans = $this->prompt("Delete appconfig." . $this->appname . ".php", 'N', 'Y/N');
				$ans = substr(strtoupper($ans), 0, 1);
				if($ans == 'Y' || $ans == 'N') break;
			}
			while(true);
			echo "\n";
			if($ans == 'Y')
			{
				echo "--> Deleting existing " . $this->appconfig . "\n";
				if(!unlink($this->appconfig)) exit(1);
			}
		}
		if(file_exists($this->appconfig))
		{
			echo "--> Skipping application configuration as appconfig." . $this->appname . ".php already exists\n";
		}
		else
		{
			$this->appconfigCreated = true;
			echo "--> Creating " . $this->appconfig . "\n";
			if(!($f = fopen($this->appconfig, 'w'))) exit(1);
			fwrite($f, '<?php' . "\n\n");
			fwrite($f, '/* Application configuration for ' . $this->appname . ' - generated by Eregansu Install at ' . strftime('%Y-%m-%d %H:%M:%S') . '*/' . "\n\n");
			fwrite($f, "define('APP_NAME', '" . $this->appname . "');\n\n");
			fclose($f);
			chmod($this->appconfig, 0644);
		}
	}
	
	protected function checkAppConfigLink()
	{
		if(file_exists(CONFIG_ROOT . 'appconfig.php'))
		{
			if(is_link(CONFIG_ROOT . 'appconfig.php'))
			{
				echo "--> appconfig.php already exists and is a symbolic link, re-creating\n";
				if(!unlink(CONFIG_ROOT . 'appconfig.php')) exit(1);
			}
			else
			{
				echo "The file appconfig.php already exists but is not a symbolic link. Do you\n";
				echo "wish to keep this file as-is, or move it out of the way and create the symbolic\n";
				echo "link to appconfig." . $this->appname . ".php?\n\n";
				do
				{
					$ans = $this->prompt("Keep appconfig.php as-is", 'N', 'Y/N');
					$ans = substr(strtoupper($ans), 0, 1);
					if($ans == 'Y' || $ans == 'N') break;
				}
				while(true);
				if($ans == 'N')
				{
					$newname = "appconfig.old-" . getmypid() . ".php";
					echo "---> Renaming appconfig.php to $newname\n";
					if(!rename(CONFIG_ROOT . 'appconfig.php', CONFIG_ROOT . $newname)) exit(1);
				}
			}
		}
		if(!file_exists(CONFIG_ROOT . 'appconfig.php'))
		{
			chdir(CONFIG_ROOT);
			echo "--> Symbolically linking appconfig." . $this->appname . ".php to appconfig.php\n";
			symlink('appconfig.' . $this->appname . '.php', 'appconfig.php');
		}
		echo "\n";	
	}
	
	public function checkInstanceName()
	{
		echo "Every copy of each Eregansu application requires an instance name which should be\n";
		echo "unique to that copy of the application. Usually, you would set the instance name\n";
		echo "to the local part of the host's hostname, but if you are going to run multiple\n";
		echo "copies of the application on the same host, then only one of them can use this\n";
		echo "default.\n";
		echo "\n";
		echo "For example, you might run two copies of an application: a development copy and a\n";
		echo "live copy. If the hostname was 'lily', then you might set the instance name of\n";
		echo "one copy to be 'lily' and the other to be 'lily-dev'.\n";
		echo "\n";
		echo "If you’re just experimenting with Eregansu and want to get set up quickly, the\n";
		echo "default value should be fine.\n\n";
		do
		{
			echo "Please enter a name consisting only of letters, numbers and dashes, 1-32 characters\n";
			$ans = $this->prompt("Instance", $this->defaultInstanceName);
			$ans = trim(strtolower($ans));
			if(strlen($ans) >= 1 && strlen($ans) <= 32)
			{
				break;
			}
		}
		while(true);
		$this->instname = $ans;		
	}
	
	public function checkConfig()
	{
		$this->config = CONFIG_ROOT . 'config.' . $this->instname . '.php';				
		if(file_exists($this->config))
		{
			echo "The file config." . $this->instname . ".php already exists. Would you like to delete it\n";
			echo "and create a new one?\n\n";
			do
			{
				$ans = $this->prompt("Delete config." . $this->instname . ".php", 'N', 'Y/N');
				$ans = substr(strtoupper($ans), 0, 1);
				if($ans == 'Y' || $ans == 'N') break;
			}
			while(true);
			echo "\n";
			if($ans == 'Y')
			{
				echo "--> Deleting existing " . $this->config . "\n";
				if(!unlink($this->config)) exit(1);
			}
		}
		if(file_exists($this->config))
		{
			echo "--> Skipping instance configuration as config." . $this->instname . ".php already exists\n";
		}
		else
		{
			$this->configCreated = true;
			echo "--> Creating " . $this->config . "\n";
			if(!($f = fopen($this->config, 'w'))) exit(1);
			fwrite($f, '<?php' . "\n\n");
			fwrite($f, '/* Instance configuration for ' . $this->instname . ' - generated by Eregansu Install at ' . strftime('%Y-%m-%d %H:%M:%S') . '*/' . "\n\n");

			fwrite($f, '/* Each copy of an application must have a unique INSTANCE_NAME' . "\n");
			fwrite($f, ' * If you use (either directly or directly) the "cluster" module,' . "\n");
			fwrite($f, ' * then INSTANCE_NAME is used to determine which cluster this' . "\n");
			fwrite($f, ' * is part of.' . "\n");
			fwrite($f, ' */' . "\n");
			fwrite($f, 'define(\'INSTANCE_NAME\', \'' . $this->instname . '\');' . "\n\n");

			fwrite($f, '/* The "heartbeat" module will use HOST_NAME in preference to INSTANCE_NAME if' . "\n");
			fwrite($f, ' * defined.' . "\n");
			fwrite($f, ' */' . "\n");
			fwrite($f, '/* define(\'HOST_NAME\', \'' . $this->instname . '\'); */' . "\n\n");

			fwrite($f, "/* With the below defined, if jQuery is used by any pages, it will be loaded\n");
			fwrite($f, " * from Google's servers. If you comment it out or remove it, Eregansu will\n");
			fwrite($f, " * expect to load jQuery from SCRIPTS_IRI/jquery/jquery-<version>/jquery.min.js\n");
			fwrite($f, " */\n");
			fwrite($f, "define('SCRIPTS_USE_GAPI', true);\n\n");
			
			fwrite($f, "/* Uncomment the below to override the path used to serve scripts,\n");
			fwrite($f, " * which by default is set to the root URL of your application.\n");
			fwrite($f, " */\n");
			fwrite($f, "/* define('SCRIPTS_IRI', 'http://static1.example.com/scripts/'); */\n\n");
			
			fwrite($f, "/* For applications which make use of the object store, set\n");
			fwrite($f, " * OBJECT_CACHE_ROOT to the absolute path of a directory where\n");
			fwrite($f, " * a JSON-encoded copy of each stored object should be written to.\n");
			fwrite($f, " * Objects are stored at <OBJECT_CACHE_ROOT>/<kind>/<uuid[0..2]>/<uuid>.json\n");
			fwrite($f, " * If specified, OBJECT_CACHE_ROOT must include a trailing slash.\n");
			fwrite($f, " */\n");
			fwrite($f, "/* define('OBJECT_CACHE_ROOT', '/shared/cache/objects/'); */\n\n");
			fclose($f);
			chmod($this->config, 0644);
		}	
	}
	
	protected function checkConfigLink()
	{
		if(file_exists(CONFIG_ROOT . 'config.php'))
		{
			if(is_link(CONFIG_ROOT . 'config.php'))
			{
				echo "--> config.php already exists and is a symbolic link, re-creating\n";
				if(!unlink(CONFIG_ROOT . 'config.php')) exit(1);
			}
			else
			{
				echo "The file config.php already exists but is not a symbolic link. Do you\n";
				echo "wish to keep this file as-is, or move it out of the way and create the symbolic\n";
				echo "link to config." . $this->instname . ".php?\n\n";
				do
				{
					$ans = $this->prompt("Keep config.php as-is", 'N', 'Y/N');
					$ans = substr(strtoupper($ans), 0, 1);
					if($ans == 'Y' || $ans == 'N') break;
				}
				while(true);
				if($ans == 'N')
				{
					$newname = "config.old-" . getmypid() . ".php";
					echo "---> Renaming config.php to $newname\n";
					if(!rename(CONFIG_ROOT . 'config.php', CONFIG_ROOT . $newname)) exit(1);
				}
			}
		}
		if(!file_exists(CONFIG_ROOT . 'config.php'))
		{
			chdir(CONFIG_ROOT);
			echo "--> Symbolically linking config." . $this->instname . ".php to instconfig.php\n";
			symlink('config.' . $this->instname . '.php', 'config.php');
		}
		echo "\n";	
	}
	
	protected function prompt($prompt, $default = null, $options = null)
	{
		static $stdin;
		
		if(!$stdin)
		{
			$stdin = fopen('php://stdin', 'r');
		}
		if($options)
		{
			$prompt .= ' (' . $options . ')';
		}
		$prompt .= '?';
		if($default)
		{
			$prompt .= ' [' . $default . '] ';
		}
		echo $prompt;
		flush();
		$line = trim(fgets($stdin));
		if(!strlen($line) && $default)
		{
			$line = $default;
		}
		echo "\n";
		flush();
		return $line;
	}
	
	protected function checkIndex()
	{
		if(file_exists(INSTANCE_ROOT . 'index.php'))
		{
			echo "--> index.php already exists, leaving untouched\n";
		}
		else
		{
			echo "--> Creating index.php\n";
			$f = fopen(INSTANCE_ROOT . 'index.php', 'w');
			fwrite($f, '<?php' . "\n\n");
			fwrite($f, '/* Generated by Eregansu Install at ' . strftime('%Y-%m-%d %H:%M:%S') . ' */' . "\n\n");
	
			fwrite($f, 'require(dirname(__FILE__) . \'/platform/platform.php\');' . "\n\n");
			
			fwrite($f, '$app->process($request);' . "\n");
			fclose($f);
			chmod(INSTANCE_ROOT . 'index.php', 0644);
		}
	}
		
	protected function checkEregansuLink()
	{
		if(file_exists(INSTANCE_ROOT . 'eregansu'))
		{
			echo "--> Re-creating eregansu script symbolic link\n";
			unlink(INSTANCE_ROOT . 'eregansu');
		}
		else
		{
			echo "--> Creating eregansu script symbolic link\n";
		}
		chdir(INSTANCE_ROOT);
		symlink($this->relPlatformPath . 'eregansu', 'eregansu');
	}
	
	protected function checkHtAccess()
	{
		if(file_exists(INSTANCE_ROOT . '.htaccess'))
		{
			echo "--> .htaccess already exists, leaving untouched\n";
		}
		else
		{
			echo "--> Generating .htaccess from platform/htaccess.dist\n";
			copy(PLATFORM_ROOT . 'htaccess.dist', INSTANCE_ROOT . '.htaccess');
			chmod(INSTANCE_ROOT . '.htaccess', 0644);
		}
	}
	
	protected function checkApp()
	{
		if(!file_exists(MODULES_ROOT))
		{
			echo "--> " . MODULES_ROOT . " does not exist, creating\n";
			mkdir(MODULES_ROOT);
			chmod(MODULES_ROOT, 0755);
			if(!file_exists(MODULES_ROOT . $this->appname))
			{
				echo "--> " . MODULES_ROOT . $this->appname . " does not exist, creating\n";
				mkdir(MODULES_ROOT . $this->appname);
				chmod(MODULES_ROOT . $this->appname, 0755);				
			}
		}
		else
		{
			echo "--> " . MODULES_ROOT . " already exists, leaving untouched\n";
		}
	}
	
	protected function checkTemplates()
	{
		$path = INSTANCE_ROOT . (defined('TEMPLATES_PATH') ? TEMPLATES_PATH : 'templates') . '/';
		if(!file_exists($path))
		{
			echo "--> " . $path . " does not exist, creating\n";
			mkdir($path, true);
			chmod($path, 0755);
			$rpath = $this->relPlatformPath . 'login/templates';
			if(substr($rpath, 0, 1) != '/')
			{
				$rpath = '../' . $rpath;
			}
			if(!file_exists($path . 'login'))
			{
				echo "--> " . $path . "login does not exist, creating\n";			
				symlink($rpath, $path . 'login');
			}
			if(!file_exists($path . 'default'))
			{
				echo "--> " . $path . "default does not exist, creating\n";
				mkdir($path . 'default');
				$files = array('home.phtml', 'test.phtml', 'header.php', 'footer.php', 'screen.css');
				foreach($files as $f)
				{
					echo "--> Creating " . $path . "default/" . $f . " from platform/examples/templates/" . $f . "\n";
					copy(PLATFORM_ROOT . "examples/templates/" . $f, $path . "default/" . $f);
				}
			}
		}
		else
		{
			echo "--> " . $path . " already exists, leaving untouched\n";
		}
	}
	
	protected function scanModules()
	{
		if(!$this->appconfigCreated && !$this->configCreated)
		{
			return;
		}
		echo "--> Scanning for modules...\n";
		
		$d = opendir(MODULES_ROOT);
		$modules = array();
		$c = 0;
		while(($de = readdir($d)))
		{
			if(substr($de, 0, 1) == '.') continue;
			if(is_dir(MODULES_ROOT . $de))
			{
				if(file_exists(MODULES_ROOT . $de . '/install.php'))
				{
					include_once(MODULES_ROOT . $de . '/install.php');
					if(!class_exists($de . 'ModuleInstall'))
					{
						echo '*** ' . MODULES_ROOT . $de . '/install.php exists but does not define a class named ' . $de . 'ModuleInstall; skipping' . "\n";
						continue;
					}
					$className = $de . 'ModuleInstall';
					$inst = new $className($this, $de, MODULES_ROOT . $de . '/');
					$k = sprintf('%04d-%04d', $inst->moduleOrder, $c);
					echo " +> Found module " . $inst->name . "\n";
					$modules[$k] = $inst;
				}
			}
			$c++;
		}
		ksort($modules);
		if($this->appconfigCreated)
		{
			$appConfig = fopen($this->appconfig, 'a');
		}
		else
		{
			$appConfig = null;
		}
		if($this->configCreated)
		{
			$config = fopen($this->config, 'a');
		}
		else
		{
			$config = null;
		}
		foreach($modules as $inst)
		{
			if($this->appconfigCreated)
			{
				$inst->writeAppConfig($appConfig);
			}
			if($this->configCreated)
			{
				$inst->writeInstanceConfig($config);
			}
			$inst->createLinks();
			$this->configuredModules++;
		}
		if($appConfig)
		{
			fclose($appConfig);
		}
		if($config)
		{
			fclose($config);
		}
	}
	
	protected function postamble()
	{
		if(!$this->configuredModules && $this->appconfigCreated)
		{
			$f = fopen($this->appconfig, 'a');
			fwrite($f, '$HTTP_ROUTES = array(' . "\n");
			fwrite($f, "\t'__NONE__' => array('class' => 'Page', 'templateName' => 'home.phtml', 'title' => 'Sample homepage'),\n");
			fwrite($f, "\t'test' => array('class' => 'Page', 'templateName' => 'test.phtml', 'title' => 'Another sample page'),\n");
			fwrite($f, ");\n");
			fclose($f);
		}
	}
}

if(file_exists(INSTANCE_ROOT . '/install.php'))
{
	require_once(INSTANCE_ROOT . '/install.php');
	if(!$installer)
	{
		trigger_error(INSTANCE_ROOT . '/install.php does not initialise $installer to be an installer class instance', E_USER_ERROR);
		exit(1);
	}
}
else
{
	$installer = new Installer();
}

if(!$installer->run())
{
	exit(1);
}

echo ">>> Installation complete\n";
