<?php

/* Copyright 2009, 2010 Mo McRoberts.
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
	}
	
	public function close()
	{
		curl_close($this->handle);
		$this->handle = null;
	}
	
	public function exec()
	{
		if(!$this->handle)
		{
			trigger_error('Curl::exec() - cannot execute a request which has been closed', E_USER_ERROR);
			return false;
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