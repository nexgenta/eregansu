<?php

/* Authentication system providing the builtin: scheme, which authenticates
 * users listed in the $BUILTIN_USERS global array.
 */
class BuiltinAuth extends Auth
{
	protected $builtinAuthScheme = true;
	protected $users = array();
	
	public function __construct()
	{
		global $BUILTIN_USERS;
		
		parent::__construct();
		if(isset($BUILTIN_USERS) && is_array($BUILTIN_USERS))
		{
			$this->users = $BUILTIN_USERS;
		}
	}
	
	
	public function verifyAuth($request, $scheme, $iri, $authData, $callbackIRI)
	{
		if(!isset($this->users[$iri]))
		{
			return new AuthError($this, null, 'User ' . $iri . ' does not exist');
		}
		if(!strcmp($this->users[$iri]['password'], crypt($authData, $this->users[$iri]['password'])))
		{
			$user = $this->users[$iri];
			if(!isset($user['scheme'])) $user['scheme'] = $scheme;
			if(!isset($user['iri'])) $user['iri'] = $scheme . ':' . $iri;
			if(!($uuid = $this->createRetrieveUserWithIRI($scheme . ':' . $iri, $user)))
			{
				return new AuthError($this, 'You cannot log into your account at this time.', 'Identity/authorisation failure');
			}
			$user['uuid'] = $uuid;
			$this->refreshUserData($user);
			return $user;
		}
		return new AuthError($this, null, 'Incorrect password supplied for user ' . $iri);
	}
	
	public function verifyToken($request, $scheme, $iri, $token)
	{
		if(!isset($this->users[$iri]))
		{
			return new AuthError($this, null, 'User ' . $iri . ' does not exist');
		}
		if(!strcmp($this->users[$iri]['password'], crypt($token, $this->users[$iri]['password'])))
		{
			$user = $this->users[$iri];
			$this->refreshUserData($user);
			return $user;
		}
		return new AuthError($this, null, 'Incorrect password (as a token) supplied for user ' . $iri);
	}
	
	public function retrieveUserData($scheme, $remainder)
	{
		if(isset($this->users[$remainder]))
		{
			return $this->users[$remainder];
		}
		return null;
	}

	public function refreshUserData(&$data)
	{
		if(!isset($data['scheme'])) $data['scheme'] = 'builtin';
		$data['iri'] = 'builtin' . ':' . $data['name'];
		$data['ttl'] = time(0) + 1; /* Force rapid refresh when we’re dealing with static config */
		parent::refreshUserData($data);
	}
}
