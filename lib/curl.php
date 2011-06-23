<?php

/* Copyright 2009-2011 Mo McRoberts.
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

if(function_exists('curl_init'))
{
	if(!defined('CURLOPT_PROTOCOLS')) define('CURLOPT_PROTOCOLS', null);
	if(!defined('CURLOPT_REDIR_PROTOCOLS')) define('CURLOPT_REDIR_PROTOCOLS', null);
	
	if(!defined('CURLPROTO_HTTP')) define('CURLPROTO_HTTP', null);
	if(!defined('CURLPROTO_HTTPS')) define('CURLPROTO_HTTPS', null);
	if(!defined('CURLPROTO_FTP')) define('CURLPROTO_FTP', null);
	if(!defined('CURLPROTO_FTPS')) define('CURLPROTO_FTPS', null);
	if(!defined('CURLPROTO_SCP')) define('CURLPROTO_SCP', null);
	if(!defined('CURLPROTO_SFTP')) define('CURLPROTO_SFTP', null);
	if(!defined('CURLPROTO_TELNET')) define('CURLPROTO_TELNET', null);
	if(!defined('CURLPROTO_LDAP')) define('CURLPROTO_LDAP', null);
	if(!defined('CURLPROTO_LDAPS')) define('CURLPROTO_LDAPS', null);
	if(!defined('CURLPROTO_DICT')) define('CURLPROTO_DICT', null);
	if(!defined('CURLPROTO_FILE')) define('CURLPROTO_FILE', null);
	if(!defined('CURLPROTO_TFTP')) define('CURLPROTO_TFTP', null);
	if(!defined('CURLPROTO_ALL')) define('CURLPROTO_ALL', null);

	class Curl
	{
		const CLOSEPOLICY_LEAST_RECENTLY_USED = CURLCLOSEPOLICY_LEAST_RECENTLY_USED;
		const CLOSEPOLICY_OLDEST = CURLCLOSEPOLICY_OLDEST;
		
		const FTPAUTH_SSL = CURLFTPAUTH_SSL;
		const FTPAUTH_TLS = CURLFTPAUTH_TLS;
		const FTPAUTH_DEFAULT = CURLFTPAUTH_DEFAULT;
		
		const HTTP_VERSION_NONE = CURL_HTTP_VERSION_NONE;
		const HTTP_VERSION_1_0 = CURL_HTTP_VERSION_1_0;
		const HTTP_VERSION_1_1 = CURL_HTTP_VERSION_1_1;
		
		const AUTH_BASIC = CURLAUTH_BASIC;
		const AUTH_DIGEST = CURLAUTH_DIGEST;
		const AUTH_GSSNEGOTIATE = CURLAUTH_GSSNEGOTIATE;
		const AUTH_NTLM = CURLAUTH_NTLM;
		const AUTH_ANY = CURLAUTH_ANY;
		const AUTH_ANYSAFE = CURLAUTH_ANYSAFE;
		
		const PROTO_HTTP = CURLPROTO_HTTP;
		const PROTO_HTTPS = CURLPROTO_HTTPS;
		const PROTO_FTP = CURLPROTO_FTP;
		const PROTO_FTPS = CURLPROTO_FTPS;
		const PROTO_SCP = CURLPROTO_SCP;
		const PROTO_SFTP = CURLPROTO_SFTP;
		const PROTO_TELNET = CURLPROTO_TELNET;
		const PROTO_LDAP = CURLPROTO_LDAP;
		const PROTO_LDAPS = CURLPROTO_LDAPS;
		const PROTO_DICT = CURLPROTO_DICT;
		const PROTO_FILE = CURLPROTO_FILE;
		const PROTO_TFTP = CURLPROTO_TFTP;
		const PROTO_ALL = CURLPROTO_ALL;
		
		const PROXY_HTTP = CURLPROXY_HTTP;
		const PROXY_SOCKS5 = CURLPROXY_SOCKS5;
		
		const TIMECOND_IFMODSINCE = CURL_TIMECOND_IFMODSINCE;
		const TIMECOND_IFUNMODSINCE = CURL_TIMECOND_IFUNMODSINCE;
		
		/* Associative array of authentication details per URL base.
		 * e.g., 'http://example.com/' => 'user:secret'
		 */
		public static $authData = array();
		
		protected static $boolProps = array(
			'autoReferer' => CURLOPT_AUTOREFERER,
			'autoReferrer' => CURLOPT_AUTOREFERER,
			'cookieSession' => CURLOPT_COOKIESESSION,
			'crlf' => CURLOPT_CRLF,
			'dnsUseGlobalCache' => CURLOPT_DNS_USE_GLOBAL_CACHE,
			'failOnError' => CURLOPT_FAILONERROR,
			'fetchFileTime' => CURLOPT_FILETIME,
			'followLocation' => CURLOPT_FOLLOWLOCATION,
			'forbidReuse' => CURLOPT_FORBID_REUSE,
			'freshConnect' => CURLOPT_FRESH_CONNECT,
			'ftpUseEPRT' => CURLOPT_FTP_USE_EPRT,
			'ftpUseEPSV' => CURLOPT_FTP_USE_EPSV,
			'ftpAppend' => CURLOPT_FTPAPPEND,
			'ftpASCII' => CURLOPT_TRANSFERTEXT,
			'ftpListOnly' => CURLOPT_FTPLISTONLY,
			'fetchHeaders' => CURLOPT_HEADER,
	/*		'trackRequestString' => CURLINFO_HEADER_OUT, */
			'httpGET' => CURLOPT_HTTPGET,
			'httpProxyTunnel' => CURLOPT_HTTPPROXYTUNNEL,
	//		'mute' => CURLOPT_MUTE,
			'netrc' => CURLOPT_NETRC,
			'noBody' => CURLOPT_NOBODY,
			'noProgress' => CURLOPT_NOPROGRESS,
			'noSignal' => CURLOPT_NOSIGNAL,
			'httpPOST' => CURLOPT_POST,
			'httpPUT' => CURLOPT_PUT,
			'returnTransfer' => CURLOPT_RETURNTRANSFER,
			'sslVerifyPeer' => CURLOPT_SSL_VERIFYPEER,
			'transferText' => CURLOPT_TRANSFERTEXT,
			'unrestrictedAuth' => CURLOPT_UNRESTRICTED_AUTH,
			'upload' => CURLOPT_UPLOAD,
			'verbose' => CURLOPT_VERBOSE,
		);
		
		protected static $intProps = array(
			'bufferSize' => CURLOPT_BUFFERSIZE,
			'closePolicy' => CURLOPT_CLOSEPOLICY,
			'connectTimeout' => CURLOPT_CONNECTTIMEOUT,
			'connectTimeoutMS' => CURLOPT_CONNECTTIMEOUT_MS,
			'dnsCacheTimeout' => CURLOPT_DNS_CACHE_TIMEOUT,
			'ftpSSLAuth' => CURLOPT_FTPSSLAUTH,
			'httpVersion' => CURLOPT_HTTP_VERSION,
			'httpAuth' => CURLOPT_HTTPAUTH,
			'inFileSize' => CURLOPT_INFILESIZE,
			'lowSpeedLimit' => CURLOPT_LOW_SPEED_LIMIT,
			'lowSpeedTime' => CURLOPT_LOW_SPEED_TIME,
			'maxConnects' => CURLOPT_MAXCONNECTS,
			'maxRedirs' => CURLOPT_MAXREDIRS,
			'port' => CURLOPT_PORT,
			'protocols' => CURLOPT_PROTOCOLS,
			'proxyAuth' => CURLOPT_PROXYAUTH,
			'proxyPort' => CURLOPT_PROXYPORT,
			'proxyType' => CURLOPT_PROXYTYPE,
			'redirProtocols' => CURLOPT_REDIR_PROTOCOLS,
			'resumeFrom' => CURLOPT_RESUME_FROM,
			'sslVerifyHost' => CURLOPT_SSL_VERIFYHOST,
			'sslVersion' => CURLOPT_SSLVERSION,
			'timeCondition' => CURLOPT_TIMECONDITION,
			'timeout' => CURLOPT_TIMEOUT,
			'timeoutMs' => CURLOPT_TIMEOUT_MS,
			'timeValue' => CURLOPT_TIMEVALUE,
		);
		
		protected static $strProps = array(
			'caInfo' => CURLOPT_CAINFO,
			'caPath' => CURLOPT_CAPATH,
			'cookie' => CURLOPT_COOKIE,
			'cookieFile' => CURLOPT_COOKIEFILE,
			'cookieJar' => CURLOPT_COOKIEJAR,
			'customRequest' => CURLOPT_CUSTOMREQUEST,
			'egdSocket' => CURLOPT_EGDSOCKET,
			'encoding' => CURLOPT_ENCODING,
			'ftpPort' => CURLOPT_FTPPORT,
			'interface' => CURLOPT_INTERFACE,
			'krb4Level' => CURLOPT_KRB4LEVEL,
			'postFields' => CURLOPT_POSTFIELDS,
			'proxy' => CURLOPT_PROXY,
			'proxyAuthData' => CURLOPT_PROXYUSERPWD,
			'randomFile' => CURLOPT_RANDOM_FILE,
			'range' => CURLOPT_RANGE,
			'referer' => CURLOPT_REFERER,
			'referrer' => CURLOPT_REFERER,
			'sslCipherList' => CURLOPT_SSL_CIPHER_LIST,
			'sslCert' => CURLOPT_SSLCERT,
			'sslCertPassword' => CURLOPT_SSLCERTPASSWD,
			'sslCertType' => CURLOPT_SSLCERTTYPE,
			'sslEngine' => CURLOPT_SSLENGINE,
			'sslEngineDefault' => CURLOPT_SSLENGINE_DEFAULT,
			'sslKey' => CURLOPT_SSLKEY,
			'sslKeyPassword' => CURLOPT_SSLKEYPASSWD,
			'sslKeyType' => CURLOPT_SSLKEYTYPE,
			'url' => CURLOPT_URL,
			'userAgent' => CURLOPT_USERAGENT,
			'authData' => CURLOPT_USERPWD,
		);
		
		protected static $arrayProps = array(
			'http200Aliases' => CURLOPT_HTTP200ALIASES,
			'headers' => CURLOPT_HTTPHEADER,
			'postQuote' => CURLOPT_POSTQUOTE,
			'quote' => CURLOPT_QUOTE,
		);
		
		protected $handle;
		protected $options = array();
		
		public static function version()
		{
			return curl_version();
		}
		
		public function __construct($url = null)
		{
			$this->handle = curl_init($url);
			$this->options['url'] = $url;
			$this->options['http200Aliases'] = $this->options['headers'] = $this->options['postQuote'] = $this->options['quote'] = array();
			foreach(self::$boolProps as $k => $i)
			{
				$this->options[$k] = false;
			}
			$this->options['dnsUseGlobalCache'] = true;
			$this->options['httpGET'] = true;
			if(defined('CURL_ALWAYS_VERBOSE') && CURL_ALWAYS_VERBOSE)
			{
				$this->__set('verbose', true);
			}
		}
		
		public function close()
		{
			curl_close($this->handle);
			$this->handle = null;
		}
		
		protected function authDataForURL($url)
		{
			$len = 0;
			$string = null;
			$slen = strlen($url);
			foreach(self::$authData as $base => $authData)
			{
				$l = strlen($base);
				if($l > $len && $l <= $slen && !strncmp($base, $url, $l))
				{
					$len = $l;
					$string = $authData;
				}
			}
			return $string;
		}
					
		public function exec()
		{
			if(!$this->handle)
			{
				trigger_error('Curl::exec() - cannot execute a request which has been closed', E_USER_ERROR);
				return false;
			}
			if(!isset($this->options['authData']))
			{
				if(null !== ($auth = $this->authDataForURL($this->options['url'])))
				{
					$this->__set('authData', $auth);
				}
			}
			return curl_exec($this->handle);
		}
		
		public function __get($name)
		{
			if($name == 'version')
			{
				return curl_version();
			}
			if(!$this->handle)
			{
				trigger_error('Curl - cannot retrieve information for a request which has been closed', E_USER_NOTICE);
				return null;
			}
			if($name == 'errno')
			{
				return curl_errno($this->handle);
			}
			if($name == 'error')
			{
				return curl_error($this->handle);
			}
			if($name == 'info')
			{
				return curl_getinfo($this->handle);
			}
			if(isset($this->options[$name]))
			{
				return $this->options[$name];
			}
			return null;
		}
		
		public function __set($name, $value)
		{
			if($name == 'version' || $name == 'errno' || $name == 'error' || $name == 'info')
			{
				trigger_error('Curl - attempt to set read-only property "' . $name . '"', E_USER_NOTICE);
				return;
			}
			if(!$this->handle)
			{
				trigger_error('Curl - cannot set information for a request which has been closed', E_USER_NOTICE);
				return null;
			}
			/* Deal with mutually-exclusive options */
			if($name == 'httpGET' || $name == 'httpPOST' || $name == 'httpPUT' || $name == 'customRequest')
			{
				$this->options['httpGET'] = $this->options['httpPOST'] = $this->options['httpPUT'] = false;
				$this->options['customRequest'] = null;
			}
			$this->options[$name] = $value;
			if(isset(self::$boolProps[$name]))
			{
				curl_setopt($this->handle, self::$boolProps[$name], $value);
				return;
			}
			if(isset(self::$intProps[$name]))
			{
				curl_setopt($this->handle, self::$intProps[$name], $value);
				return;
			}
			if(isset(self::$strProps[$name]))
			{
				curl_setopt($this->handle, self::$strProps[$name], $value);
				return;
			}
			if(isset(self::$arrayProps[$name]))
			{
				curl_setopt($this->handle, self::$arrayProps[$name], $value);
				return;
			}
			trigger_error('Warning: attempt to set undefined Curl option ' . $name, E_USER_WARNING);
		}		
	}

	class CurlCache extends Curl
	{
		public $cacheDir = null;
		public $cacheTime = null;
		public $cacheErrors = false;
		protected $cachedInfo = null;

		public function exec()
		{
			$this->cachedInfo = null;
			$fetch = true;
			$store = true;
			$dir = $this->cacheDir;
			$time = $this->cacheTime;
			$cacheFile = null;
			$info = null;
			if(!strlen($dir))
			{
				if(defined('CACHE_DIR'))
				{
					$dir = CACHE_DIR;
				}
				else
				{
					trigger_error('Warning: $this->cacheDir is not set and CACHE_DIR is not defined', E_USER_WARNING);
					$fetch = false;
					$store = false;
				}
			}
			if(substr($dir, -1) != '/')
			{
				$dir .= '/';
			}
			if($time === null)
			{
				if(defined('CACHE_TIME'))
				{
					$time = intval(CACHE_TIME);
				}
				else
				{
					$time = 600;
				}
			}
			else
			{
				$time = intval($time);
			}
			if(empty($this->options['httpGET']))
			{
				$fetch = false;
				$store = false;
			}
			if($fetch || $store)
			{
				$hash = md5(json_encode($this->options));
				$cacheFile = $dir . $hash;
			}
			if(strlen($cacheFile) && file_exists($cacheFile) && file_exists($cacheFile . '.json'))
			{
				if($time > 0)
				{
					$info = stat($cacheFile);
					if($info['mtime'] + $time > time())
					{
						$fetch = false;
					}
				}
				if($fetch)
				{
					unlink($cacheFile);
					unlink($cacheFile . '.json');
				}
			}
			if($fetch)
			{
				$buf = parent::exec();
				$info = curl_getinfo($this->handle);
				if($store && ($buf !== false || $this->cacheErrors))
				{
					$f = fopen($cacheFile, 'w');
					fwrite($f, $buf);
					fclose($f);
					$f = fopen($cacheFile . '.json', 'w');
					fwrite($f, json_encode($info));
					fclose($f);
					$info['fetched'] = true;
				}
			}
			else if(strlen($cacheFile))
			{
				$buf = file_get_contents($cacheFile);
				$info = json_decode(file_get_contents($cacheFile . '.json'), true);
				$info['cacheFile'] = $cacheFile;
			}
			else
			{
				$buf = $info = null;
			}
			$this->cachedInfo = $info;
			return $buf;
		}

		public function __get($name)
		{
			if($name == 'info')
			{
				return $this->cachedInfo;
			}
			return parent::__get($name);
		}

	}
}
