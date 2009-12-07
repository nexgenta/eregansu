<?php

/* Login page
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


uses('form', 'auth');

if(!defined('DEFAULT_LOGIN_KIND')) define('DEFAULT_LOGIN_KIND', 'openid');

class LoginPage extends Page
{
	protected $skin = 'login';
	protected $templateName = 'login.phtml';
	protected $loginForm;
	protected $supportedMethods = array('GET', 'POST');
	protected $loginKinds = array('openid' => 'an OpenID', 'default' => 'an E-mail address and password');
	protected $kind = null;
	protected $defaultSchemes = array();
	
	public function __construct()
	{
		parent::__construct();
		$this->defaultSchemes['openid'] = 'https';
	}
	
	protected function getObject()
	{
		if(isset($this->request->params[1]) && isset($this->request->objects[0]) && !strcmp($this->request->params[0], 'return'))
		{
			/* Validate and perform callback */
			$scheme = $this->request->params[1];
			$token = $this->request->objects[0];
			if(isset($this->session->loginIRI) && isset($this->session->loginIRI[$token]) && !strcmp($this->session->loginIRI[$token][0], $scheme))
			{
				$engine = Auth::authEngineForScheme($scheme);
				if(!$engine)
				{
					$this->loginForm()->errors[] = 'Incorrect sign-in name or password';
					return true;
				}
				if(($udata = $engine->callback($this->request, $scheme, $this->session->loginIRI[$token][1])))
				{
					if($udata instanceof Exception)
					{
						$this->loginForm()->errors[] = $udata->getMessage();
					}
					else
					{
						$this->setIdentity($udata);
					}
				}
				else
				{
					$this->loginForm()->errors[] = 'Incorrect sign-in name or password';
				}
			}
		}
		else if(isset($this->request->params[0]))
		{
			$this->kind = $this->request->params[0];
		}
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();

		if(!isset($this->loginKinds[$this->kind])) $this->kind = null;
		if($this->kind == null && isset($this->session->loginKind))
		{
			$this->kind = $this->session->loginKind;
		}
		if(!isset($this->loginKinds[$this->kind])) $this->kind = DEFAULT_LOGIN_KIND;
		$this->session->begin();
		$this->session->loginKind = $this->kind;
		$this->session->commit();
		if(!empty($this->session->user))
		{
			if(!empty($this->request->query['logout']))
			{
				$this->session->begin();
				$this->session->user = null;
				$this->session->commit();
			}
			return $this->redirect();
		}
		$this->useChromaHash();
		$this->loginForm();
		$urlparams = array();
		if(isset($this->request->query['redirect']))
		{
			$this->loginForm['redirect'] = $this->request->query['redirect'];
			$urlparams[] = 'redirect=' . urlencode($this->request->query['redirect']);
		}
		if(isset($this->defaultSchemes[$this->kind]))
		{
			$this->loginForm['defaultScheme'] = $this->defaultSchemes[$this->kind];
		}
		$urlparams[] = 'sid=' . urlencode($this->session->sid);		
		$this->vars['loginKinds'] = $this->loginKinds;
		$this->vars['kind'] = $this->kind;
		$this->vars['page_type'] = 'login login-' . $this->kind;
		$this->vars['page_title'] = 'Sign in';
		$this->vars['loginForm'] = $this->loginForm->render($this->request);
		$this->vars['urlparams'] = implode(';', $urlparams);
	}
	
	protected function redirect()
	{
		if(isset($this->request->postData['redirect']))
		{
			$this->request->redirect($this->request->postData['redirect'], 302, true);
		}
		else if(isset($this->request->query['redirect']))
		{
			$this->request->redirect($this->request->query['redirect'], 302, true);
		}
		else
		{
			$this->request->redirect($this->request->base, 302, true);
		}
		return;
	}
	
	protected function performSubmission()
	{
		$iri = $this->loginForm['iri'];
		$authData = $this->loginForm['auth'];
		$scheme = null;
		$engine = Auth::authEngineForIRI($iri, $scheme);
		if(!$engine)
		{
			$this->loginForm->errors[] = 'Incorrect sign-in name or password';
			return false;
		}
		$sessionToken = md5($this->session->nonce . $iri);
		$this->session->begin();
		if(!isset($this->session->loginIRI)) $this->session->loginIRI = array();
		$this->session->loginIRI[$sessionToken] = array($scheme, $iri);
		$callback = $this->request->absolutePage . 'return/' . $scheme . '/-/' . $sessionToken;
		$this->session->commit();
		$udata = $engine->verifyAuth($this->request, $scheme, $iri, $authData, $callback);
		if($udata instanceof Exception)
		{
			$this->loginForm->errors[] = $udata->getMessage();
			return false;
		}
		if(!$udata)
		{
			$this->loginForm->errors[] = 'Incorrect sign-in name or password';
			return false;
		}
		$this->setIdentity($udata);
	}
	
	protected function setIdentity($userData)
	{
		$this->session->begin();
		$this->session->user = $userData;
		unset($this->session->loginIRI);
		unset($this->session->nonce);
		$this->session->commit();	
	}
	
	protected function getForms()
	{
		$this->forms['login'] = $this->loginForm();
	}
	
	protected function loginForm()
	{
		if(!$this->loginForm)
		{
			$this->loginForm = new Form('login');
			if($this->kind == 'openid')
			{
				$this->loginForm->field(array('name' => 'iri', 'type' => 'text', 'label' => 'OpenID:', 'required' => true));
				$this->loginForm->field(array('name' => 'auth', 'type' => 'hidden', 'required' => false));
			}
			else
			{
				$this->loginForm->field(array('name' => 'iri', 'type' => 'text', 'label' => 'E-mail address:', 'required' => true));
				$this->loginForm->field(array('name' => 'auth', 'type' => 'password', 'label' => 'Password:', 'required' => false));
			}
			$this->loginForm->field(array('name' => 'redirect', 'type' => 'hidden'));
			$this->loginForm->submit('Sign in');
		}
		return $this->loginForm;
	}
}
