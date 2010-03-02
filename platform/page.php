<?php

/* Eregansu: (HTML) pages
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

class Page extends Proxy
{
	public $tpl;
	
	protected $title;
	protected $templateName;
	protected $vars = array();
	protected $skin = null;
	protected $defaultSkin = null;
	protected $supportedTypes = array('text/html');
	protected $scripts = array();
	protected $forms = array();
	protected $submittedForms = array();
	protected $links = array();
	
	protected function perform_POST($type)
	{
		if($this->checkSubmission())
		{
			$this->performSubmission();
		}
		return true;
	}
	
	protected function perform_GET($type)
	{
		if($type == 'text/html')
		{
			$templateName = $this->templateName;
			if(!strlen($templateName))
			{
				if(isset($this->request->data['templateName']))
				{
					$templateName = $this->request->data['templateName'];
				}
				else
				{
					$templateName = get_class($this) . '.phtml';
				}
			}
//			header('Content-type: text/html');
			$this->request->header('Content-type', $type);
			$skin = $this->skin;
			if($skin === null && isset($this->request->data['skin']))
			{
				$skin = $this->request->data['skin'];
			}
			if($skin === null && $this->request->app && $this->request->app->skin !== null)
			{
				$skin = $this->request->app->skin;
			}
			$this->tpl = new Template($this->request, $templateName, $skin, $this->defaultSkin);
			$this->vars = $this->request->data;
			$this->assignTemplate();
			$this->vars['page'] = $this;
			$this->vars['scripts'] = $this->scripts;
			$this->vars['links'] = $this->links;
			$this->vars['objects'] = $this->objects;
			$this->vars['object'] = $this->object;
			$this->tpl->setArray($this->vars);
			$this->tpl->process();
			$this->tpl->reset();
			$this->tpl = null;
			return;
		}
		return parent::perform_GET($type);
	}
	
	protected function assignTemplate()
	{
		if(!strlen($this->title) && isset($this->request->data['title']))
		{
			$this->title = $this->request->data['title'];
		}
		$this->vars['alerts'] = array();
		$this->vars['app'] = $this->request->app;
		$this->vars['app_root'] = $this->request->base;
		$this->vars['site_root'] = $this->request->root;
		$this->vars['uri'] = $this->request->uri;
		$this->vars['pageUri'] = $this->request->pageUri;
		$this->vars['request'] = $this->request;
		$this->vars['session'] = $this->session;
		$this->vars['crumb'] =& $this->request->crumb;
		$this->vars['backRef'] =& $this->request->backRef;
		$this->vars['page_title'] = $this->title;
		if($this->session)
		{
			$this->vars['qusid'] = $this->session->qusid;
			$this->vars['usid'] = $this->session->usid;
		}
		else
		{
			$this->vars['qusid'] = null;
			$this->vars['usid'] = null;
		}
		if(isset($this->request->page[0]))
		{
			$this->vars['section'] = $this->request->page[0];
		}
		else
		{
			$this->vars['section'] = 'home';
		}
	}
	
	protected function getForms()
	{
	}
	
	protected function checkSubmission()
	{
		$this->getForms();
		if(!isset($this->request->postData['__name'])) return false;
		if(!is_array($this->request->postData['__name']) || !count($this->request->postData['__name'])) return false;
		$ok = true;
		foreach($this->request->postData['__name'] as $fname)
		{
			$this->submittedForms[$fname] = $fname;
			if(!isset($this->forms[$fname]) || !$this->forms[$fname]->checkSubmission($this->request))
			{
				$ok = false;
			}
		}
		return $ok;
	}
	
	protected function performSubmission()
	{
	}
	
	/* Javascript libraries */
	protected function useJQuery($version = '1.4.1')
	{
		$root = $this->request->root;
		if(defined('SCRIPTS_IRI')) $root = SCRIPTS_IRI;
		if(defined('SCRIPTS_USE_GAPI')) $root = 'http://ajax.googleapis.com/ajax/libs/';
		$this->scripts['jquery'] = $root . 'jquery/' . $version . '/jquery.min.js';
	}
	
	protected function useGlitter($module)
	{
		$this->useJQuery();
		$root = $this->request->root;
		if(defined('SCRIPTS_IRI')) $root = SCRIPTS_IRI;
		$this->scripts['glitter/' . $module] = $root . 'glitter/' . $module . '.js';
	}
	
	protected function useGlow($module = 'core', $version = '1.7.0')
	{
		static $hasCSS = array('1.7.0' => array('widgets' => true));

		$root = $this->request->root;
		if(defined('SCRIPTS_IRI')) $root = SCRIPTS_IRI;
		if($module != 'core' && !isset($this->scripts['glow-core']))
		{
			$this->useGlow('core', $version);
		}
		$this->scripts['glow-' . $module] = $root . 'glow/' . $version . '/' . $module . '/' . $module . '.js';
		if(!empty($hasCSS[$version][$module]))
		{
			$this->links['glow-' . $module] = array(
				'rel' => 'stylesheet',
				'type' => 'text/css', 
				'href' => $root . 'glow/' . $version . '/' . $module . '/' . $module . '.css',
				'media' => 'screen');
		}
	}
	
	protected function useChromaHash()
	{
		$this->useJQuery();
		$root = $this->request->root;
		if(defined('SCRIPTS_IRI')) $root = SCRIPTS_IRI;
		$this->scripts['chroma-hash'] = $root . 'Chroma-Hash/chroma-hash.js';
	}
}
