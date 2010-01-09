<?php

/* Eregansu: Fatal errors
 *
 * Copyright 2009 Mo McRoberts.
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

/**
 * @framework Eregansu
 */

if(!defined('TEMPLATES_PATH')) define('TEMPLATES_PATH', 'templates');

class TerminalErrorException extends Exception
{
}

class Error implements IRequestProcessor
{
	const BAD_REQUEST = 400;
	const AUTHORIZATION_REQUIRED = 401;
	const PAYMENT_REQUIRED = 402;
	const FORBIDDEN = 403;
	const OBJECT_NOT_FOUND = 404;
	const ROUTE_NOT_MATCHED = 404.1;
	const NO_OBJECT = 404.2;
	const METHOD_NOT_ALLOWED = 405;
	const TYPE_NOT_SUPPORTED = 406;
	const PROXY_AUTHENTICATION_REQUIRED = 407;
	const REQUEST_TIMEOUT = 408;
	const CONFLICT = 409;
	const GONE = 410;
	const LENGTH_REQUIRED = 411;
	const PRECONDITION_FAILED = 412;
	const REQUEST_TOO_LARGE = 413;
	const URI_TOO_LONG = 414;
	const UNSUPPORTED_MEDIA_TYPE = 415;
	const RANGE_NOT_SATISFIABLE = 416;
	const EXPECTATION_FAILED = 417;
	
	const INTERNAL = 500;
	const NOT_IMPLEMENTED = 501;
	const METHOD_NOT_IMPLEMENTED = 501.1;
	const BAD_GATEWAY = 502;
	const SERVICE_UNAVAILABLE = 503;
	const ROUTE_NOT_PROCESSOR = 503.1;
	const GATEWAY_TIMEOUT = 504;
	const HTTP_VERSION_NOT_SUPPORTED = 505;
	const VARIANT_ALSO_NEGOTIATES = 506;
	const INSUFFICIENT_STORAGE = 507;
	const BANDWIDTH_LIMIT_EXCEEDED = 508;
	const NOT_EXTENDED = 509;
	
	public static $throw = false;
	
	protected static $titles = array(
		self::BAD_REQUEST => 'Bad request',
		self::AUTHORIZATION_REQUIRED => 'Authorization required',
		self::PAYMENT_REQUIRED => 'Payment required',
		self::FORBIDDEN => 'Forbidden',
		self::OBJECT_NOT_FOUND => 'Object not found',
		self::ROUTE_NOT_MATCHED => 'Route not matched',
		self::NO_OBJECT => 'No object specified',
		self::METHOD_NOT_ALLOWED => 'Method not allowed',
		self::TYPE_NOT_SUPPORTED => 'Type not supported',
		self::PROXY_AUTHENTICATION_REQUIRED => 'Proxy authentication required',
		self::REQUEST_TIMEOUT => 'Request timed out',
		self::CONFLICT => 'Conflict',
		self::GONE => 'Gone',
		self::LENGTH_REQUIRED => 'Length required',
		self::PRECONDITION_FAILED => 'Precondition failed',
		self::REQUEST_TOO_LARGE => 'Request too large',
		self::URI_TOO_LONG => 'URI too long',
		self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported media type',
		self::RANGE_NOT_SATISFIABLE => 'Range not satisfiable',
		self::EXPECTATION_FAILED => 'Expectation failed',
		
		self::INTERNAL => 'Internal error',
		self::NOT_IMPLEMENTED => 'Not implemented',
		self::METHOD_NOT_IMPLEMENTED => 'Method not implemented',
		self::BAD_GATEWAY => 'Bad gateway',
		self::SERVICE_UNAVAILABLE => 'Service unavailable',
		self::GATEWAY_TIMEOUT => 'Gateway timed out',
		self::HTTP_VERSION_NOT_SUPPORTED => 'HTTP version not supported',
		self::VARIANT_ALSO_NEGOTIATES => 'Variant also negotiates',
		self::INSUFFICIENT_STORAGE => 'Insufficient storage',
		self::BANDWIDTH_LIMIT_EXCEEDED => 'Bandwidth limit exceeded',
		self::NOT_EXTENDED => 'Not extended',
	);
	
	/* %1$s is the requested object (with trailing space)
	 * %2$s is the request method
	 * %3$s is the request content type
	 */
	protected static $descriptions = array(
		self::BAD_REQUEST => 'Your request could not be processed because it was malformed.',
		self::AUTHORIZATION_REQUIRED => 'The requested object is not available without authentication.',
		self::PAYMENT_REQUIRED => 'Payment is required to access the requested object.',
		self::FORBIDDEN => 'Access to the object %1$swas denied.',
		self::OBJECT_NOT_FOUND => 'The requested object %1$scould not be found.',
		self::METHOD_NOT_ALLOWED => 'Your request could not be processed because the method %3$s is not supported by this object.',
		self::TYPE_NOT_SUPPORTED => 'Your request could not be processed because the requested type is not supported by the object %1$s',
		
		self::UNSUPPORTED_MEDIA_TYPE => 'Your request could not be processed because the submitted type %3$s is not supported by this object',
	);
	
	public $status = self::INTERNAL;
	public $detail = null;
	public $object = null;
	
	public function __construct($aStatus = null)
	{
		if($aStatus && !is_object($aStatus))
		{
			$this->status = $aStatus;
		}
	}
	
	public function process(Request $req)
	{	
		$title = $this->statusTitle($this->status);
		$desc = $this->statusDescription($this->status, $req);
		@header('HTTP/1.0 ' . floor($this->status) . ' ' . $title);
		if(!isset($req->types) || !in_array('text/html', $req->types) && !in_array('*/*', $req->types))
		{
			@header('Content-type: text/plain');			
			echo $title . " (" . $this->status . ")\n\n";
			if($this->detail)
			{
				echo $this->detail . "\n";
			}
			else
			{
				echo $desc . "\n";
			}
			if(self::$throw) throw new TerminalErrorException($title, $this->status);
			exit(1);
		}
		if(isset($req->data['errorSkin']) && isset($req->siteRoot) && file_exists($req->siteRoot . 'templates/' . $req->data['errorSkin'] . '/error.php'))
		{
			$this->errorTemplate($req, $req->data['errorSkin'], $title, $desc);
		}
		else if(defined('DEFAULT_ERROR_SKIN') && isset($req->siteRoot))
		{
			$path = $req->siteRoot . TEMPLATES_PATH . '/' . DEFAULT_ERROR_SKIN . '/error.php';
			if(file_exists($path))
			{
				$this->errorTemplate($req, DEFAULT_ERROR_SKIN, $title, $desc);
			}
		}
		@header('Content-type: text/html;charset=UTF-8');
		echo '<!DOCTYPE html>' . "\n";
		echo '<html>';
		echo '<head>';
		echo '<meta http-equiv="Content-type" value="text/html;charset=UTF-8" />';
		echo '<title>' . $title . '</title>';
		echo '</head>';
		echo '<body>';
		echo '<h1>' . _e($title) . '</h1>';
		echo '<p>' . _e($desc)  . '</p>';
		echo '</body>';
		echo '</html>';
		exit(1);
	}
	
	protected function errorTemplate(Request $request, $skin, $title, $desc)
	{
		error_reporting(0);
		$templates_iri = $request->root . 'templates/';
		$skin_iri = $template_root . $skin . '/';
		$templates_path = $request->siteRoot . TEMPLATES_PATH . '/';
		$skin_path = $templates_Path . $skin . '/';
		$detail = $this->detail;
		require($request->siteRoot . TEMPLATES_PATH . '/' . $skin . '/error.php');
		exit(1);
	}
	
	protected function statusTitle($code)
	{
		if(isset(self::$titles[$code])) return self::$titles[$code];
		$code = floor($code);
		if(isset(self::$titles[$code])) return self::$titles[$code];
		return 'Error';		
	}
	
	protected function statusDescription($code, Request $req)
	{
		$method = $req->method;
		if(isset($req->postData['__method'])) $method = $req->postData['__method'];
		if($this->object)
		{
			$object = $this->object;
		}
		else
		{
			$object = $req->uri;
		}
		if(strlen($object))
		{
			$object = $object . ' ';
		}
		if(isset(self::$descriptions[$code])) return sprintf(self::$descriptions[$code], $object, $method, $req->contentType);
		$code = floor($code);
		if(isset(self::$descriptions[$code])) return sprintf(self::$descriptions[$code], $object, $method, $req->contentType);
		if(strlen($object))
		{
			return 'An error occurred while attempting to process a ' . $method . ' request on ' . $object;
		}
		return 'An error occurred while attempting to process a ' . $method . ' request';
	}
}