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
	public $defaultAppName = null;
	public $defaultInstanceName = null;
	
	public $relPlatformPath;
	public $relModulesPath;
	public $relPublicPath;

	public function __construct()
	{
		if(!strlen($this->defaultInstanceName))
		{
			$hostname = explode('.', php_uname('n'));		
			$this->defaultInstanceName = $hostname[0];
		}
		if(!strlen($this->defaultAppName))
		{
			if(file_exists(CONFIG_ROOT . 'appconfig.php'))
			{
				if(($f = fopen(CONFIG_ROOT . 'appconfig.php', 'r')))
				{
					$str = fread($f, 4096);
					fclose($f);
					$matches = array();
					if(preg_match('/^\s*define\(\s*(\'APP_NAME\'|"APP_NAME")\s*,\s*(\'([^\']+)\'|"([^"]+)")\s*\)\s*;\s*$/m', $str, $matches))
					{
						if(isset($matches[3]))
						{
							$this->defaultAppName = $matches[3];
						}
					}
				}
			}
		}
		if(!strlen($this->defaultAppName))
		{
			$this->defaultAppName = 'default';
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
		$this->checkPublicRoot();
		$this->checkIndex();
		$this->checkEregansuLink();
		$this->checkCLILink();
		$this->checkHtAccess();
		$this->checkApache2Config();
		$this->checkLighttpdConfig();
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
		echo " - Public root:         " . PUBLIC_ROOT . "\n";
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
		if(!strncmp(INSTANCE_ROOT, PUBLIC_ROOT, strlen(INSTANCE_ROOT)))
		{
			$this->relPublicPath = substr(PUBLIC_ROOT, strlen(INSTANCE_ROOT));
		}
		else
		{
			$this->relPublicPath = PUBLIC_ROOT;
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

	protected function checkPublicRoot()
	{
		if(!strcmp(INSTANCE_ROOT, PUBLIC_ROOT))
		{
			return;
		}
		if(file_exists(PUBLIC_ROOT))
		{
			echo "--> " . PUBLIC_ROOT . " already exists.\n\n";
		}
		else
		{
			echo "--> " . PUBLIC_ROOT . " does not exist, creating.\n\n";
			if(!mkdir(PUBLIC_ROOT)) exit(1);
			chmod(PUBLIC_ROOT, 0755);
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
		if(file_exists(PUBLIC_ROOT . 'index.php'))
		{
			echo "--> index.php already exists, leaving untouched\n";
		}
		else
		{
			echo "--> Creating index.php\n";
			$f = fopen(PUBLIC_ROOT . 'index.php', 'w');
			fwrite($f, '<?php' . "\n\n");
			fwrite($f, '/* Generated by Eregansu Install at ' . strftime('%Y-%m-%d %H:%M:%S') . ' */' . "\n\n");
			fwrite($f, "define('INSTANCE_ROOT', realpath(dirname(__FILE__) . '/..') . '/');\n");
			fwrite($f, "define('PUBLIC_ROOT', realpath(dirname(__FILE__)) . '/');\n\n");

			fwrite($f, 'require(INSTANCE_ROOT . \'/eregansu/platform.php\');' . "\n\n");

			fwrite($f, '$app->process($request);' . "\n");
			fclose($f);
			chmod(PUBLIC_ROOT . 'index.php', 0644);
		}
	}
		
	protected function checkCLILink()
	{
		if(file_exists(INSTANCE_ROOT . 'cli'))
		{
			echo "--> Re-creating command-line script symbolic link\n";
			unlink(INSTANCE_ROOT . 'cli');
		}
		else
		{
			echo "--> Creating command-line script symbolic link\n";
		}
		chdir(INSTANCE_ROOT);
		symlink($this->relPlatformPath . 'cli', 'cli');
	}

	protected function checkEregansuLink()
	{
		if(file_exists(INSTANCE_ROOT . 'eregansu'))
		{
			if(!is_link(INSTANCE_ROOT . 'eregansu') && is_dir(INSTANCE_ROOT . 'eregansu'))
			{
				echo "--> " . INSTANCE_ROOT . "eregansu/ is a directory, leaving untouched\n";
				return;
			}
			echo "--> Re-creating eregansu script symbolic link\n";			
			unlink(INSTANCE_ROOT . 'eregansu');
		}
		else
		{
			echo "--> Creating Eregansu platform symbolic link\n";
		}
		chdir(INSTANCE_ROOT);
		symlink($this->relPlatformPath, 'eregansu');
	}
	
	protected function checkHtAccess()
	{
		if(file_exists(PUBLIC_ROOT . '.htaccess'))
		{
			echo "--> .htaccess already exists, leaving untouched\n";
		}
		else
		{
			echo "--> Generating .htaccess from platform/htaccess.dist\n";
			copy(PLATFORM_ROOT . 'htaccess.dist', PUBLIC_ROOT . '.htaccess');
			chmod(PUBLIC_ROOT . '.htaccess', 0644);
		}
	}

	protected function checkApache2Config()
	{
		if(file_exists(CONFIG_ROOT . 'apache2.conf'))
		{
			echo "--> " . CONFIG_ROOT . "apache2.conf already exists, leaving untouched\n";
			return;
		}
		echo "--> Generating sample Apache 2.x virtual host configuration\n";
		$f = fopen(CONFIG_ROOT . 'apache2.conf', 'w');
		fwrite($f, "<VirtualHost *:80>\n" .
			   "ServerName " . $this->appname . "\n" .
			   "DocumentRoot " . PUBLIC_ROOT . "\n" .
			   "DirectoryIndex index.php\n" .
			   "</VirtualHost>\n\n" .

			   "<Directory " . PUBLIC_ROOT . ">\n" .
			   "Order allow,deny\n" .
			   "Allow from all\n" .
			   "Options +FollowSymLinks\n" .
			   "AllowOverride all\n" .
			   "</Directory>\n\n"
			);
		fclose($f);
	}

	protected function checkLighttpdConfig()
	{
		if(file_exists(CONFIG_ROOT . 'lighttpd.conf'))
		{
			echo "--> " . CONFIG_ROOT . "lighttpd.conf already exists, leaving untouched\n";
			return;
		}
		echo "--> Generating sample Lighttpd virtual host configuration\n";
		$f = fopen(CONFIG_ROOT . 'lighttpd.conf', 'w');
		fwrite($f, "\$HTTP[\"host\"] =~ \"^" . str_replace('.', '\.', $this->appname) . "$\" {\n" .
			   "\tserver.document-root = \"" . PUBLIC_ROOT . "\"\n" .
			   "\turl.rewrite-once = (\n" .
			   "\t\t\"^(?!/((templates|media|data|content)/.*|favicon.ico|slate.manifest))\" => \"/index.php\"\n" .
			   "\t)\n" .
			   "}\n");
		fclose($f);
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
		$path = PUBLIC_ROOT . (defined('TEMPLATES_PATH') ? TEMPLATES_PATH : 'templates') . '/';
		if(!file_exists($path))
		{
			echo "--> " . $path . " does not exist, creating\n";
			mkdir($path, true);
			chmod($path, 0755);
			$rpath = $this->relPlatformPath . 'login/templates';
			if(substr($rpath, 0, 1) != '/')
			{
				$rpath = '../../' . $rpath;
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
		echo "*** Configuration complete ***\n\n";
		echo "Apache 2.x users: See " . CONFIG_ROOT . "apache2.conf for a sample virtual host configuration.\n";
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
