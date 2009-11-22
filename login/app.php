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

class LoginPage extends Page
{
	protected $skin = 'login';
	protected $templateName = 'login.phtml';
	protected $loginForm;
	protected $supportedMethods = array('GET', 'POST');
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		
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
		if(isset($this->request->query['redirect']))
		{
			$this->loginForm['redirect'] = $this->request->query['redirect'];
		}
		$this->vars['page_type'] = 'login';
		$this->vars['page_title'] = 'Sign in';
		$this->vars['loginForm'] = $this->loginForm->render($this->request);
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
			die('No engine for iri');
			return false;
		}
		$udata = $engine->verifyAuth($this->request, $scheme, $iri, $authData);
		if(!$udata)
		{
			die('Login failed');
			return false;
		}
//		syslog(LOG_CRIT, "LoginPage::performSubmission(): Logging in as " . $iri);
		$this->session->begin();
		$this->session->user = $udata;
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
			$this->loginForm->field(array('name' => 'iri', 'type' => 'text', 'label' => 'E-mail address:', 'required' => true));
			$this->loginForm->field(array('name' => 'auth', 'type' => 'password', 'label' => 'Password:', 'required' => true));
			$this->loginForm->field(array('name' => 'redirect', 'type' => 'hidden'));
			$this->loginForm->submit('Sign in');
		}
		return $this->loginForm;
	}
}
