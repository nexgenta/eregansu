<?php

/* Eregansu: (HTML) pages
 *
 * Copyright 2009-2012 Mo McRoberts.
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

/**
 * @year 2009-2012
 * @include uses('page');
 * @since Available in Eregansu 1.0 and later. 
 */

/**
 * Templated web page generation.
 */

class Page extends Proxy
{
	public $tpl;
	
	protected $title;
	protected $templateName;
	protected $vars = array();
	protected $skin = null;
	protected $defaultSkin = null;
	protected $theme = null;
	protected $defaultTheme = null;
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
	
	protected function perform_GET_HTML($type = 'text/html')
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
		$this->request->header('Content-type', 'text/html; charset=UTF-8');
		$skin = $this->inheritProperty('skin');
		$theme = $this->inheritProperty('theme');
		$this->tpl = new Template($this->request, $templateName, $skin, $this->defaultSkin, $theme, $this->defaultTheme);
		$this->vars = array_merge($this->request->data, $this->tpl->vars);
		$this->vars['scripts'] =& $this->scripts;
		$this->vars['links'] =& $this->links;
		$this->tpl->setArrayRef($this->vars);
		$this->assignTemplate();
		$this->vars['page'] = $this;
		$this->vars['objects'] = $this->objects;
		$this->vars['object'] = $this->object;
		$this->tpl->process();
		$this->tpl->reset();
		$this->tpl = null;
	}
	
	protected function inheritProperty($propertyName)
	{
		if(strlen($this->{$propertyName}))
		{
			return $this->{$propertyName};
		}
		if(isset($this->request->data[$propertyName]))
		{
			return $this->request->data[$propertyName];
		}
		if(isset($this->request->app) && strlen($this->request->app->{$propertyName}))
		{
			return $this->request->app->{$propertyName};
		}
		return null;
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
		$this->vars['proxyUri'] = $this->proxyUri;
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
		$this->tpl->useJQuery($version);
	}
	
	protected function useGlitter($module)
	{
		$this->tpl->useGlitter($module);
	}
	
	protected function useGlow($module = 'core', $version = '1.7.0')
	{
		$this->tpl->useGlow($module, $version);
	}
	
	protected function useChromaHash()
	{
		$this->tpl->useChromaHash();
	}
}
