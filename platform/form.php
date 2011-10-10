<?php

/* Copyright 2009-2011 Mo McRoberts
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
 * @framework EregansuCore Eregansu Core Library
 * @author Mo McRoberts <mo.mcroberts@nexgenta.com>
 * @year 2009, 2010, 2011
 * @copyright Mo McRoberts
 * @include uses('form');
 * @sourcebase http://github.com/nexgenta/eregansu/blob/master/
 * @since Available in Eregansu 1.0 and later. 
 */
 
/**
 * HTML form generation and handling
 */

class Form implements ArrayAccess
{
	protected $fields = array(); /**< @internal */
	protected $actions = array(); /**< @internal */
	protected $name = 'form'; /**< @internal */
	public $includeHiddenFields = null;
	public $action = null;
	public $errorCount = 0;
	public $method = 'POST';
	public $prefix;
	public $notices = array();
	public $errors = array();

	public function __construct($name)
	{
		$this->name = $name;
		$this->prefix = $name . '_';
	}
	
	public function values()
	{
		$values = array();
		foreach($this->fields as $k => $f)
		{
			if(isset($f['value']))
			{
				$values[$f['name']] = $f['value'];
			}
		}
		return $values;
	}

	public function checkSubmission($req)
	{
		$this->action = null;
		$this->errorCount = 0;
		if($this->method == 'GET')
		{
			$data = $req->query;
		}
		else
		{
			$data = $req->postData;
		}
		foreach($this->actions as $act)
		{
			if(!empty($data[$this->prefix . $act['name']]))
			{
				$this->action = $act;
				break;
			}
		}
		foreach($this->fields as $k => $f)
		{
			if(isset($data[$this->prefix . $f['name']]))
			{
				$fv = trim($data[$this->prefix . $f['name']]);
				if(strlen($fv))
				{
					$this->fields[$k]['rawValue'] = $fv;
					if(!$this->process($fv, $this->fields[$k]))
					{
						$this->fields[$k]['error'] = true;
						$this->errorCount++;
					}
					
				}
				else if(!empty($f['required']))
				{
					$f['error'] = true;
					$this->errorCount++;
				}
			}
		}
		if($this->errorCount)
		{
			return false;
		}
		return true;
	}

	protected function process($value, &$info)
	{
		$info['rawValue'] = $value;
		$tvalue = trim($value);
		$success = false;
		if(!empty($info['trim']))
		{
			$value = $tvalue;
		}
		switch(@$info['type'])
		{
		case 'int':
			if(!ctype_digit($tvalue))
			{
				$success = false;
			}
			$value = intval($tvalue);
			break;
		case 'float':
			$value = floatval(trim($value));
			break;
		case 'checkbox':
			if(!strcmp($value, $info['checkValue']))
			{
				$info['checked'] = true;
			}
			break;
		case 'text':
		case 'search':
		case 'textarea':
			break;
		}
		$info['value'] = $value;
		return $success;
	}
	
	public function field($info)
	{
		if(!isset($info['name']) || !isset($info['type']))
		{
			return;
		}
		$this->fields[$info['name']] = $info;
	}
	
	public function submit($label, $name = 'go')
	{
		$this->actions[] = array('type' => 'submit', 'label' => $label, 'name' => $name);
	}
	
	public function cancel($url, $label = 'go back')
	{
		$this->actions[] = array('type' => 'cancel', 'url' => $url, 'label' => $label);
	}
	
	public function render($req, $multiple = false, $prefix = null)
	{
		$buf = array();
		
		$htmethod = $method = trim(strtoupper($this->method));
		if($method != 'GET' && $method != 'POST')
		{
			$htmethod = 'POST';
		}
		if(!$multiple) $buf[] = '<form method="' . _e($htmethod) . '" action="' . _e($req->uri) . '">';
		if(count($this->notices))
		{
			$buf[] = '<ul class="notices">';
			foreach($this->notices as $notice)
			{
				$buf[] = '<li>' . _e($notice) . '</li>';
			}
			$buf[] = '</ul>';
		}
		if(count($this->errors))
		{
			$buf[] = '<ul class="errors">';
			foreach($this->errors as $error)
			{
				$buf[] = '<li>' . _e($error) . '</li>';
			}
			$buf[] = '</ul>';
		}
		if(($hidden = $this->includeHiddenFields) === null)
		{
			$hidden = ($this->method != 'GET');
		}
		if($hidden)
		{			
			$buf[] = '<input type="hidden" name="__name[]" value="' . _e($this->name) . '" />';
			$buf[] = '<input type="hidden" name="__method" value="' . _e($method) . '" />';
			if(isset($req->session) && isset($req->session->fieldName))
			{
				$buf[] = '<input type="hidden" name="' . _e($req->session->fieldName) . '" value="' . _e($req->session->sid) . '" />';
			}
		}
		foreach($this->fields as $field)
		{
			$this->preprocess($field);
			switch($field['type'])
			{
				case 'hidden':
					$this->renderHidden($buf, $req, $field);
					break;
				case 'textarea':
					$this->renderTextArea($buf, $req, $field);
					break;
				case 'password':
					$this->renderPassword($buf, $req, $field);
					break;
				case 'label':
					$this->renderLabel($buf, $req, $field);
					break;
				case 'select':
					$this->renderSelect($buf, $req, $field);
					break;
				case 'checkbox':
					$this->renderCheckbox($buf, $req, $field);
					break;
				default:
					$this->renderText($buf, $req, $field);
					break;
					
			}
		}
		if(count($this->actions))
		{
			$buf[] = '<fieldset class="actions">';
			$or = false;
			foreach($this->actions as $act)
			{
				if($act['type'] == 'submit')
				{
					$or = true;
					$buf[] = '<input type="submit" name="' . htmlspecialchars($act['name']) . '" value="' . htmlspecialchars($act['label']) . '" />';
				}
				else if($act['type'] == 'cancel')
				{
					if($or)
					{
						$p = ' or ';
						$label = $act['label'];
					}
					else
					{
						$p = '';
						$label = strtoupper(substr($act['label'], 0, 1)) . substr($act['label'], 1);
					}
					$p .= '<a href="' . htmlspecialchars($act['url']) . '">' . htmlspecialchars($label) . '</a>';
					$buf[] = $p;
				}
			}
			$buf[] = '</fieldset>';
		}
		if(!$multiple) $buf[] = '</form>';
		return implode("\n", $buf);
	}
	
	/**
	 * @internal
	 */
	protected function preprocess(&$info)
	{
		if(!isset($info['value']))
		{
			if(isset($info['defaultValue']))
			{
				$info['value'] = $info['defaultValue'];
			}
			else
			{
				$info['value'] = null;
			}
		}
		if(!isset($info['htmlName']))
		{
			$info['htmlName'] = htmlspecialchars($this->prefix . $info['name']);
			if(isset($info['index']))
			{
				$info['htmlName'] .= '[' . htmlspecialchars($info['index']) . ']';
			}
		}
		if(!isset($info['id']))
		{
			$info['id'] = $info['name'];
			if(isset($info['index']))
			{
				$info['id'] .= '-' . $info['index'];
			}
		}
		if(!isset($info['htmlId']))
		{
			$info['htmlId'] = htmlspecialchars($this->name . '-' . $info['id']);
		}
		if(!isset($info['suffix']))
		{
			$info['suffix'] = '';
		}
		if(!isset($info['htmlSuffix']))
		{
			$s = trim($info['suffix']);
			if(strlen($s))
			{
				$info['htmlSuffix'] = ' ' . $s;
			}
			else
			{
				$info['htmlSuffix'] = '';
			}
		}
		
	}
	
	/**
	 * @internal
	 */
	protected function renderVisible(&$buf, $req, $info, $el)
	{
		$class = 'field field-' . $info['type'];
		if(!isset($info['label']))
		{
			$class .= ' unlabelled';
		}
		$buf[] = '<div class="' . $class . '" id="f-' . $info['htmlId'] . '">';
		$pre = $aft = null;
		if(isset($info['label']) && (empty($info['after']) || !empty($info['contains'])))
		{
			$pre = '<label for="' . $info['htmlId'] . '">';
			if(empty($info['after']))
			{
				$pre .= htmlspecialchars($info['label']) . '&nbsp';
			}
			if(empty($info['contains']))
			{
				$pre .= '</label>';
			}
		}
		if(isset($info['label']) && (!empty($info['after']) || !empty($info['contains'])))
		{
			if(empty($info['contains']))
			{
				$aft .= '<label for="' . $info['htmlId'] . '">';
			}
			if(!empty($info['after']))
			{
				$aft .= '&nbsp;' . htmlspecialchars($info['label']);
			}
			$aft .= '</label>';
		}
		$buf[] = $pre . $el . $aft;

		$buf[] = '</div>';
	}
	
	/**
	 * @internal
	 */	
	protected function renderHidden(&$buf, $req, $info)
	{
		$buf[] = '<input id="' . $info['htmlId'] . '" type="hidden" name="' . $info['name'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />';
	}
	
	/**
	 * @internal
	 */	
	protected function renderText(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="' . $info['type'] . '" name="' . $info['htmlName'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />');
	}

	/**
	 * @internal
	 */
	protected function renderCheckbox(&$buf, $req, $info)
	{
		if(!isset($info['checkValue'])) $info['checkValue'] = 1;
		$checked = (strcmp($info['checkValue'], $info['value']) ? '' : ' checked="checked"');
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="checkbox" name="' . $info['htmlName'] . '" value="' . _e($info['checkValue']) . '"' . $checked . $info['htmlSuffix'] . ' />');
	}

	/**
	 * @internal
	 */
	protected function renderTextArea(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<textarea id="' . $info['htmlId'] . '" type="text" name="' . $info['htmlName'] . '">' . htmlspecialchars($info['value']) . '</textarea>' . $info['htmlSuffix']);
	}

	/**
	 * @internal
	 */
	protected function renderPassword(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="password" name="' . $info['htmlName'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />');
	}

	/**
	 * @internal
	 */
	protected function renderLabel(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<p>' . htmlspecialchars($info['value']) . '</p>');
	}
	
	/**
	 * @internal
	 */
	protected function renderSelect(&$buf, $req, $info)
	{
		$sbuf = array('<select id="' . $info['htmlId'] . '" name="' . $info['htmlName'] . '">');
		if(isset($info['nosel'])) $sbuf[] = '<option value="">' . _e($info['nosel']) . '</option>';
		foreach($info['from'] as $k => $v)
		{
			$s = (!strcmp($k, $info['value']));
			$sbuf[] = '<option ' . ($s?'selected="selected" ':'') . 'value="'.  _e($k) . '">' . _e($v) . '</option>';
		}
		$sbuf[] = '</select>';
		$this->renderVisible($buf, $req, $info, implode("\n", $sbuf));
	}
	
	/**
	 * @internal
	 */
	public function offsetExists($ofs)
	{
		foreach($this->fields as $f)
		{
			if(!strcmp($ofs, $f['name'])) return true;
		}
		foreach($this->actions as $f)
		{
			if(!strcmp($ofs, $f['name'])) return true;
		}
		return false;
	}
	
	/**
	 * @internal
	 */
	public function offsetGet($ofs)
	{
		foreach($this->fields as $f)
		{
			if(!strcmp($ofs, $f['name']))
			{
				if(isset($f['value']) && strlen($f['value'])) return $f['value'];
				if(isset($f['defaultValue']) && strlen($f['defaultValue'])) return $f['defaultValue'];
				return null;
			}
		}
		if(isset($this->action) && !strcmp($this->action['name'], $f))
		{
			return true;
		}
		foreach($this->actions as $f)
		{
			if(!strcmp($ofs, $f['name'])) return false;
		}
		return null;
	}
	
	/**
	 * @internal
	 */
	public function offsetSet($ofs, $value)
	{
		foreach($this->fields as $k => $f)
		{
			if(!strcmp($ofs, $f['name']))
			{
				$this->fields[$k]['defaultValue'] = $value;
				return;
			}
		}	
	}
	
	/**
	 * @internal
	 */
	public function offsetUnset($ofs)
	{
	}

}