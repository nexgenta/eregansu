<?php

uses('model', 'uuid');

/* Eregansu: Identity management and authorisation
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

/* Note regarding the difference between this functionality and that in
 * auth.php:
 *
 * The code in auth.php is concerned with authenticating users. That is,
 * ensuring that a user is who they claim to be. This can be accomplished
 * through a number of means, ranging from a simple username and password,
 * through OpenID, to POSIX-based authentication which uses the credentials
 * of the system’s currently logged-in user.
 *
 * In contrast, this functionality is focussed on authorisation and identity.
 * In other words, taking an abstract, but authenticated, string relating to a
 * user, and determining which resources they have permission to access, and
 * storing ancilliary information about them (for example, common “user
 * profile” data).
 *
 * The user identity database can be read-write, or read-only. In the latter
 * case, the database is read once when needed, and no new entries can be added
 * to it. This means that although a user may authenticate successfully (e.g.,
 * their password is correct), they will not have permission to access anything
 * and if they are not listed in the file containing identity details, and so the
 * process of logging in will ultimately fail.
 *
 * If a read-write database is employed (for example, identity information is
 * stored within a MySQL database), then users can optionally be automatically
 * added to this database once they have successfully authenticated. They will
 * still not have any special permissions—an administrator must grant them—but
 * they will be able to log in nonetheless.
 *
 * The definition IDENTITY_IRI specifies the fully-qualified IRI to the
 * identity database itself. If a file: IRI is specified, identity details are
 * read from the specified file path, which is expected to be an XML file.
 */
 
class Identity extends Model
{
	public $writeable = true;
	public $allowAutomaticCreation = false;
	
	public static function getInstance($args = null, $className = null)
	{
		if(null === $args) $args = array();
		if(!isset($args['db'])) $args['db'] = IDENTITY_IRI;
		if(null === $className)
		{
			if(!strncmp($args['db'], 'file:', 5))
			{
				$className = 'IdentityFile';				
			}
			else if(!strncmp($args['db'], 'ldap:', 5))
			{
				$className = 'IdentityDirectory';				
			}
			else
			{
				$className = 'Identity';
			}
		}
		return Model::getInstance($args, $className);
	}

	public function __construct($args)
	{
		parent::__construct($args);
		if(defined('IDENTITY_ALLOW_AUTOMATIC_CREATION') && IDENTITY_ALLOW_AUTOMATIC_CREATION)
		{
			$this->allowAutomaticCreation = true;
		}
	}
	
	protected function builtinAuthScheme($scheme)
	{
		if($scheme == 'posix' || $scheme == 'builtin') return true;
		return false;
	}
	
}

/* This class implements a read-only identity database read from an XML file.
 * Note that users authenticating using the 'builtin:' and 'posix:' schemes
 * will always pass identity checks, because the authentication layers are
 * capable of providing the required details themselves. These authentication
 * schemes will function even if no identity system is in use at all (that is,
 * IDENTITY_IRI is not defined).
 */
 
class IdentityFile extends Identity
{
	protected $users = array();
	protected $iriMap = array();
	
	public function __construct($args)
	{
		$this->writeable = false;
		$path = $args['db'];
		if(strncmp($args['db'], 'file:', 5))
		{
			trigger_error('Invalid file: IRI passed to IdentityFile::__construct()', E_USER_ERROR);
			return;
		}
		$path = substr($args['db'], 5);
		while(substr($path, 0, 2) == '//') $path = substr($path, 1);
		if(!strlen($path))
		{		
			trigger_error('Empty file: IRI passed to IdentityFile::__construct()', E_USER_ERROR);
			return;
		}
		if($path[0] != '/') $path = CONFIG_ROOT . $path;
		$this->readUsersFromFile($path);
	}
	
	protected function readUsersFromFile($path)
	{
		$xml = simplexml_load_file($path);
		foreach($xml->user as $user)
		{
			$info = array();
			foreach($user->children() as $node)
			{
				$k = $node->getName();
				$v = trim($node);
				if(isset($info[$k]))
				{
					if(!is_array($info[$k])) $info[$k] = array($info[$k]);
					$info[$k][] = $v;
				}
				else
				{
					$info[$k] = $v;
				}
			}
			$name = null;
			if(isset($info['iri']) && !is_array($info['iri'])) $info['iri'] = array($info['iri']);
			if(isset($info['iri']))
			{
				foreach($info['iri'] as $iri)
				{
					$name = $iri;
					break;
				}
			}
			if(!isset($info['uuid']))
			{
				if(!$name) $name = '(anonymous)';
				trigger_error('Cannot import user identity ' . $name . ' because it has no UUID', E_USER_NOTICE);
				continue;
			}
			if(is_array($info['uuid']))
			{
				if(!$name) $name = '(anonymous)';
				trigger_error('User identity ' . $name . ' has multiple UUIDs; using first', E_USER_WARNING);
				foreach($info['uuid'] as $uu)
				{
					$info['uuid'] = $uu;
					break;
				}
			}
			if(!$name)
			{
				$name = $info['uuid'];
			}
			else
			{
				$name .= ' (' . $info['uuid'] . ')';
			}
			if(isset($this->user[$info['uuid']]))
			{
				trigger_error('User identity ' . $name . ' matches a UUID used by another identity: the more recent entry will overwrite the earlier one', E_USER_WARNING);
			}
			if(!isset($info['iri']))
			{
				trigger_error('Cannot import user identity ' . $name . ' because it has no IRIs', E_USER_NOTICE);
				continue;
			}
			$this->user[$info['uuid']] = $info;
			foreach($info['iri'] as $iri)
			{
				if(isset($this->iriMap[$iri]))
				{
					trigger_error('User identity ' . $name . ' includes an IRI (' . $iri . ') which is already defined; this will overwrite the earlier definition', E_USER_WARNING);
				}
				$this->iriMap[$iri] = $info['uuid'];
			}
		}
	}
	
	public function uuidFromIRI($iri, $data = null)
	{
		if(isset($this->iriMap[$iri])) return $this->iriMap[$iri];
		if(isset($data['uuid']) && isset($data['scheme']) && $this->builtinAuthScheme($data['scheme']))
		{
			return $data['uuid'];
		}
		return null;
	}
	
	public function identityFromUUID($uuid, $data = null)
	{
		if(isset($this->user[$uuid])) return $this->user[$uuid];
		if(isset($data['uuid']) && isset($data['scheme']) && $this->builtinAuthScheme($data['scheme']))
		{
			return $data;
		}
		return null;
	}
	
	public function createIdentity($iri, $data = null, $automatic = false)
	{
		if(isset($this->iriMap[$iri]))
		{
			return $this->iriMap[$iri];
		}
		if(isset($data['uuid']) && isset($data['scheme']) && $this->builtinAuthScheme($data['scheme']))
		{
			if(!isset($this->users[$data['uuid']]))
			{
				$this->users[$data['uuid']] = $data;
			}
			$this->iriMap[$iri] = $data['uuid'];
			if(!in_array($iri, $this->users[$data['uuid']]['iri']))
			{
				$this->users[$data['uuid']]['iri'][] = $iri;
			}
			return $data['uuid'];
		}	
		return null;
	}
}

/* Identity/authorisation database using an LDAP database */
class IdentityDirectory extends Identity
{
	public function uuidFromIRI($iri, $data = null)
	{
		if(!($entry = $this->db->row(null, 'uid', '(&(userIRI=?)(objectClass=?))', $iri, 'userIdentity')))
		{
			return null;
		}
		if(is_array($entry['uid'])) return $entry['uid'][0];
		return $entry['uid'];
	}
	
	public function identityFromUUID($uuid, $data = null)
	{
		if(!($entry = $this->db->row(null, null, '(&(uid=?)(objectClass=?))', $uuid, 'userIdentity')))
		{
			return null;
		}
		if(!isset($entry['uid']) || !isset($entry['userIRI'])) return null;
		$entry['uuid'] = $entry['uid'];
		$entry['iri'] = $entry['userIRI'];
		if(!is_array($entry['iri'])) $entry['iri'] = array($entry['iri']);
		if(is_array($entry['uuid'])) $entry['uuid'] = $entry['uuid'][0];
		if(isset($entry['userPermission'])) $entry['perms'] = $entry['userPermission'];
		if(isset($entry['perms']) && !is_array($entry['perms']))
		{
			$entry['perms'] = array($entry['perms']);
		}
		unset($entry['uid']);
		unset($entry['iri']);
		unset($entry['userPermission']);
		return $entry;
	}
	
	public function createIdentity($iri, $data = null, $automatic = false)
	{
		if($this->allowAutomaticCreation || isset($data['scheme']) && $this->builtinAuthScheme($data['scheme']))
		{
			$uuid = UUID::generate();
			$this->db->insert('uid=' . $uuid, array(
				'objectClass' => array('account', 'top', 'userIdentity', 'uidObject'),
				'uid' => $uuid,
				'userIRI' => $iri,
			));
			return $uuid;
		}
		return null;
	}
	
	public function refreshUserData(&$data)
	{
		if(!($entry = $this->identityFromUUID($data['uuid'], $data)))
		{
			return false;
		}
		$data = array_merge($data, $entry);
		return true;
	}
}