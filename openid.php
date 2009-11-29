<?php

uses('uuid');

if(!defined('OPENID_ROOT')) define('OPENID_ROOT', INSTANCE_ROOT . 'OpenID/');

/* Eregansu: Simple OpenID consumer
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

/* Portions of this code (marked below with “Taken from the Zend Framework”) 
 * additionally carry the following license:
 *
 * Copyright (c) 2005-2009, Zend Technologies USA, Inc.
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 * 
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 * 
 *     * Neither the name of Zend Technologies USA, Inc. nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
 
class OpenIDAuth extends Auth
{
	public function verifyAuth($request, $scheme, $remainder, $authData, $callbackIRI)
	{
		global $pape_policy_uris;
		if($scheme == 'openid') $scheme = 'https';
		$iri = $scheme . ':' . $remainder;
		if(!($normalised = $this->normalise($iri)))
		{
			return new AuthError($this, 'Unable to discover the OpenID server for ' . $iri, 'Unable to normalise ' . $iri);
		}
		if(!($server = $this->discover($normalised)))
		{
			return new AuthError($this, 'Unable to discover the OpenID server for ' . $iri);
		}
		$request->session->begin();
		if(!isset($request->session->openIdInfo)) $request->session->openIdInfo = array();
		$request->session->openIdInfo[$iri] = $server;
		$request->session->commit();
		$params = array(
			'openid.mode' => 'checkid_setup',
			'openid.identity' => $server['id'],
			'openid.claimed_id' => $server['originalId'],
			'openid.return_to' => $callbackIRI,
		);
		if($server['version'] >= 2.0)
		{
			$params['openid.realm'] = $request->absoluteBase;
		}
		else
		{
			$params['openid.trust_root'] = $request->absoluteBase;		
		}
		$url = $server['server'];
		$p = '';
		foreach($params as $k => $v)
		{
			$p .= '&' . urlencode($k) . '=' . urlencode($v);
		}
		if(strpos($url, '?') !== false)
		{
			$url .= $p;
		}
		else
		{
			$url .= '?' . substr($p, 1);
		}
		$request->redirect($url);
		exit();
	}

	public function callback($request, $scheme, $remainder)
	{
		$iri = $scheme . ':' . $remainder;
		if(!isset($request->query['openid_mode'])) return false;
		if($request->query['openid_mode'] == 'id_res')
		{
			if(isset($request->query['openid_user_setup_url']) && strlen($request->query['openid_user_setup_url']))
			{
				$request->redirect($request->query['openid_user_setup_url']);
				exit();
			}
			if(!isset($request->session->openIdInfo[$iri]))
			{
				return AuthError($this, 'Could not validate your OpenID', 'The IRI ' . $iri . ' was not found in the current session');
			}
			$server = $request->session->openIdInfo[$iri];
			if(empty($request->query['openid_return_to']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_return_to was not included in the in_res response');
			}
			if(empty($request->query['openid_signed']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_signed was not included in the in_res response');
			}
			if(empty($request->query['openid_sig']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_sig was not included in the in_res response');
			}
			if(empty($request->query['openid_assoc_handle']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_assoc_handle was not included in the in_res response');
			}
			if($server['version'] >= 2.0)
			{
				if(empty($request->query['openid_response_nonce']))
				{
					return new AuthError($this, 'Could not validate your OpenID', 'openid_response_nonce was not included in the in_res response');
				}
				if(empty($request->query['openid_op_endpoint']))
				{
					return new AuthError($this, 'Could not validate your OpenID', 'openid_op_endpoint was not included in the in_res response');
				}
			}
			if(empty($request->query['openid_assoc_handle']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_assoc_handle was not included in the in_res response');
			}
			if(isset($request->query['openid_identity']) && strcmp($request->query['openid_identity'], $server['id']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_identity does not match originally-requested identity');
			}
			if(isset($request->query['openid_op_endpoint']) && strcmp($request->query['openid_op_endpoint'], $server['server']))
			{
				return new AuthError($this, 'Could not validate your OpenID', 'openid_op_endpoint does not match originally-queried server endpoint');
			}
			$params = array();
			foreach($request->query as $k => $v)
			{
				if(strncmp($k, 'openid_', 7)) continue;
				$k = str_replace('openid_ns_', 'openid.ns.', $k);
				$k = str_replace('openid_sreq_', 'openid.sreq.', $k);
				$k = str_replace('openid_', 'openid.', $k);
				$params[$k] = $v;
			}
			$params['openid.mode'] = 'check_authentication';
			$result = $this->request($server['server'], 'POST', $params, $status);
			if($status != 200)
			{
				return new AuthError($this, 'Could not validate your OpenID', 'check_authentication request failed with a status of ' . $status);
			}
			$result = explode("\n", $result);
			$response = array();
			foreach($result as $line)
			{
				$line = trim($line);
				if(!strlen($line)) continue;
				$fields = explode(':', $line, 2);
				if(count($fields) > 1)
				{
					$response[trim($fields[0])] = trim($fields[1]);
				}
			}
			if(isset($response['is_valid']) && !strcmp($response['is_valid'], 'true'))
			{
				$info = $request->session->openIdInfo[$iri];
				$user = array(
					'scheme' => 'openid',
				);
				if(strcmp($info['originalId'], $info['id']))
				{
					$user['iri'] = array($info['originalId'], $info['id']);
				}
				else
				{
					$user['iri'] = array($info['id']);
				}
				if(!($uuid = $this->createRetrieveUserWithIRI($user['iri'], $user)))
				{
					return new AuthError($this, 'You cannot log into your account at this time.', 'Identity/authorisation failure');
				}
				$user['uuid'] = $uuid;
				$this->refreshUserData($user);
				return $user;				
			}
			return new AuthError($this, 'Could not validate your OpenID', 'is_valid was present or not true');
		}
	}
	
	/** Taken from the Zend Framework
	 *
     * Normalizes OpenID identifier that can be URL or XRI name.
     * Returns the ID on success and false of failure.
     *
     * Normalization is performed according to the following rules:
     * 1. If the user's input starts with one of the "xri://", "xri://$ip*",
     *    or "xri://$dns*" prefixes, they MUST be stripped off, so that XRIs
     *    are used in the canonical form, and URI-authority XRIs are further
     *    considered URL identifiers.
     * 2. If the first character of the resulting string is an XRI Global
     *    Context Symbol ("=", "@", "+", "$", "!"), then the input SHOULD be
     *    treated as an XRI.
     * 3. Otherwise, the input SHOULD be treated as an http URL; if it does
     *    not include a "http" or "https" scheme, the Identifier MUST be
     *    prefixed with the string "http://".
     * 4. URL identifiers MUST then be further normalized by both following
     *    redirects when retrieving their content and finally applying the
     *    rules in Section 6 of [RFC3986] to the final destination URL.
     */
	protected function normalise($id)
	{
        $id = trim($id);
        if (strlen($id) === 0) {
            return $id;
        }

        // 7.2.1
        if (strpos($id, 'xri://$ip*') === 0) {
            $id = substr($id, strlen('xri://$ip*'));
        } else if (strpos($id, 'xri://$dns*') === 0) {
            $id = substr($id, strlen('xri://$dns*'));
        } else if (strpos($id, 'xri://') === 0) {
            $id = substr($id, strlen('xri://'));
        }

        // 7.2.2
        if ($id[0] == '=' ||
            $id[0] == '@' ||
            $id[0] == '+' ||
            $id[0] == '$' ||
            $id[0] == '!') {
            return $id;
        }

        // 7.2.3
        if (strpos($id, "://") === false) {
            $id = 'http://' . $id;
        }

        // 7.2.4
        return $this->normaliseURL($id);		
	}
	
    /** Taken from the Zend Framework
     * Normalizes URL according to RFC 3986 to use it in comparison operations.
     * It returns the normalised URL on success and false of failure.
     *
     * From Zend/OpenID.php
     */
    public function normaliseUrl($id)
    {
        // RFC 3986, 6.2.2.  Syntax-Based Normalization

        // RFC 3986, 6.2.2.2 Percent-Encoding Normalization
        $i = 0;
        $n = strlen($id);
        $res = '';
        while ($i < $n) {
            if ($id[$i] == '%') {
                if ($i + 2 >= $n) {
                    return false;
                }
                ++$i;
                if ($id[$i] >= '0' && $id[$i] <= '9') {
                    $c = ord($id[$i]) - ord('0');
                } else if ($id[$i] >= 'A' && $id[$i] <= 'F') {
                    $c = ord($id[$i]) - ord('A') + 10;
                } else if ($id[$i] >= 'a' && $id[$i] <= 'f') {
                    $c = ord($id[$i]) - ord('a') + 10;
                } else {
                    return false;
                }
                ++$i;
                if ($id[$i] >= '0' && $id[$i] <= '9') {
                    $c = ($c << 4) | (ord($id[$i]) - ord('0'));
                } else if ($id[$i] >= 'A' && $id[$i] <= 'F') {
                    $c = ($c << 4) | (ord($id[$i]) - ord('A') + 10);
                } else if ($id[$i] >= 'a' && $id[$i] <= 'f') {
                    $c = ($c << 4) | (ord($id[$i]) - ord('a') + 10);
                } else {
                    return false;
                }
                ++$i;
                $ch = chr($c);
                if (($ch >= 'A' && $ch <= 'Z') ||
                    ($ch >= 'a' && $ch <= 'z') ||
                    $ch == '-' ||
                    $ch == '.' ||
                    $ch == '_' ||
                    $ch == '~') {
                    $res .= $ch;
                } else {
                    $res .= '%';
                    if (($c >> 4) < 10) {
                        $res .= chr(($c >> 4) + ord('0'));
                    } else {
                        $res .= chr(($c >> 4) - 10 + ord('A'));
                    }
                    $c = $c & 0xf;
                    if ($c < 10) {
                        $res .= chr($c + ord('0'));
                    } else {
                        $res .= chr($c - 10 + ord('A'));
                    }
                }
            } else {
                $res .= $id[$i++];
            }
        }

        if (!preg_match('|^([^:]+)://([^:@]*(?:[:][^@]*)?@)?([^/:@?#]*)(?:[:]([^/?#]*))?(/[^?#]*)?((?:[?](?:[^#]*))?)((?:#.*)?)$|', $res, $reg)) {
            return false;
        }
        $scheme = $reg[1];
        $auth = $reg[2];
        $host = $reg[3];
        $port = $reg[4];
        $path = $reg[5];
        $query = $reg[6];
        $fragment = $reg[7]; /* strip it */

        if (empty($scheme) || empty($host)) {
            return false;
        }

        // RFC 3986, 6.2.2.1.  Case Normalization
        $scheme = strtolower($scheme);
        $host = strtolower($host);

        // RFC 3986, 6.2.2.3.  Path Segment Normalization
        if (!empty($path)) {
            $i = 0;
            $n = strlen($path);
            $res = "";
            while ($i < $n) {
                if ($path[$i] == '/') {
                    ++$i;
                    while ($i < $n && $path[$i] == '/') {
                        ++$i;
                    }
                    if ($i < $n && $path[$i] == '.') {
                        ++$i;
                        if ($i < $n && $path[$i] == '.') {
                            ++$i;
                            if ($i == $n || $path[$i] == '/') {
                                if (($pos = strrpos($res, '/')) !== false) {
                                    $res = substr($res, 0, $pos);
                                }
                            } else {
                                    $res .= '/..';
                            }
                        } else if ($i != $n && $path[$i] != '/') {
                            $res .= '/.';
                        }
                    } else {
                        $res .= '/';
                    }
                } else {
                    $res .= $path[$i++];
                }
            }
            $path = $res;
        }

        // RFC 3986,6.2.3.  Scheme-Based Normalization
        if ($scheme == 'http') {
            if ($port == 80) {
                $port = '';
            }
        } else if ($scheme == 'https') {
            if ($port == 443) {
                $port = '';
            }
        }
        if (empty($path)) {
            $path = '/';
        }

        $id = $scheme
            . '://'
            . $auth
            . $host
            . (empty($port) ? '' : (':' . $port))
            . $path
            . $query;
        return $id;
    }
    
    /** Taken from the Zend Framework
     * Discover the OpenID server details associated with an OpenID URL.
     */
    protected function discover($id)
    {
        $response = $this->request($id, 'GET', array(), $status);
        if ($status != 200 || !is_string($response))
        {
            return false;
        }
        $info = array('id' => $id, 'originalId' => $id);
        if (preg_match(
                '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.provider[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                $response,
                $r)) {
            $info['version'] = 2.0;
            $info['server'] = $r[3];
        } else if (preg_match(
                '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.provider[ \t]*[^"\']*\\3[^>]*\/?>/i',
                $response,
                $r)) {
            $info['version'] = 2.0;
            $info['server'] = $r[2];
        } else if (preg_match(
                '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.server[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                $response,
                $r)) {
            $info['version'] = 1.1;
            $info['server'] = $r[3];
        } else if (preg_match(
                '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.server[ \t]*[^"\']*\\3[^>]*\/?>/i',
                $response,
                $r)) {
            $info['version'] = 1.1;
            $info['server'] = $r[2];
        } else {
            return false;
        }
        if ($info['version'] >= 2.0) {
            if (preg_match(
                    '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.local_id[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                    $response,
                    $r)) {
                $info['id'] = $r[3];
            } else if (preg_match(
                    '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid2.local_id[ \t]*[^"\']*\\3[^>]*\/?>/i',
                    $response,
                    $r)) {
                $info['id'] = $r[2];
            }
        } else {
            if (preg_match(
                    '/<link[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.delegate[ \t]*[^"\']*\\1[^>]*href=(["\'])([^"\']+)\\2[^>]*\/?>/i',
                    $response,
                    $r)) {
                $info['id'] = $r[3];
            } else if (preg_match(
                    '/<link[^>]*href=(["\'])([^"\']+)\\1[^>]*rel=(["\'])[ \t]*(?:[^ \t"\']+[ \t]+)*?openid.delegate[ \t]*[^"\']*\\3[^>]*\/?>/i',
                    $response,
                    $r)) {
                $info['id'] = $r[2];
            }
        }
        $info['expires'] = time() + 60 * 60;
        return $info;
    }
    
	protected function request($url, $method = 'GET', array $params = array(), &$status = null)
	{
		$p = '';
		foreach($params as $k => $v)
		{
			$p .= '&' . urlencode($k) . '=' . urlencode($v);
		}
		if($method == 'GET')
		{
			if(strpos($url, '?') !== false)
			{
				$url .= $p;
			}
			else
			{
				$url .= '?' . substr($p, 1);
			}
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPGET, ($method == 'GET'));
		curl_setopt($ch, CURLOPT_POST, ($method == 'POST'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if($method == 'POST')
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, substr($p, 1));
		}
		$result = curl_exec($ch);
		if(($e = curl_errno($ch)) != 0)
		{
			$status = $e;
		}
		else
		{
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		}
		curl_close($ch);
		return $result;
	}

}