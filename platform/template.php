<?php

/* Eregansu: Template engine
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

class Template
{
	public $path;
	public $request;
	public $vars = array();
	protected $skin;
	
	public function __construct($req, $filename, $skin = null, $defaultSkin = null)
	{
		$this->request = $req;
		if(!strlen($skin))
		{
			if(defined('DEFAULT_SKIN'))
			{
				$skin = DEFAULT_SKIN;
			}
			else if(strlen($defaultSkin))
			{
				$skin = $defaultSkin;
			}
			else
			{
				$skin = 'default';
			}
		}
		$this->skin = $skin;
		$this->reset();
		$this->path = $this->vars['skin_path'] . $filename;
	}
	
	/* Merge the contents of $vars with the existing template variables */
	public function setArray($vars)
	{
		$this->vars = array_merge($vars, $this->vars);
	}
	
	/* Reset the template variables to their initial values */
	public function reset()
	{
		$this->vars = array();
		$this->vars['templates_path'] = $this->request->siteRoot . TEMPLATES_PATH . '/';
		$this->vars['skin_path'] = $this->vars['templates_path'] . $this->skin . '/';
		$this->vars['templates_iri'] = (defined('STATIC_IRI') ? STATIC_IRI : $this->request->root . TEMPLATES_PATH . '/');
		$this->vars['skin_iri'] = $this->vars['templates_iri'] . $this->skin . '/';
		$this->vars['skin'] = $this->skin;
	}
	
	/* Render a template */
	public function process()
	{
		global $_EREGANSU_TEMPLATE;
		
		$__ot = !empty($_EREGANSU_TEMPLATE);
		extract($this->vars);
		$_EREGANSU_TEMPLATE = true;
		require($this->path);
		$_EREGANSU_TEMPLATE = $__ot;
	}
}
