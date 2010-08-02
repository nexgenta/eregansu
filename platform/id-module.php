<?php

if(!defined('IDENTITY_IRI')) define('IDENTITY_IRI', null);

class IdentityModule extends Module
{
	public $moduleId = 'com.nexgenta.eregansu.identity';
	public $latestVersion = 1;
	
	public static function getInstance($args = null)
	{
		if(!isset($args['class'])) $args['class'] = 'IdentityModule';
		if(!isset($args['db'])) $args['db'] = IDENTITY_IRI;
		return parent::getInstance($args);
	}
	
	public function __construct($args)
	{
		if(!strncmp($args['db'], 'file:', 5) || !strncmp($args['db'], 'ldap:', 5))
		{
			$args['db'] = null;
		}
		parent::__construct($args);
	}
	
	public function dependencies()
	{
		$this->depend('com.nexgenta.eregansu.store');
	}
	
	public function updateSchema($targetVersion)
	{
		if($targetVersion == 1)
		{
			$t = $this->db->schema->tableWithOptions('object_identity', DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Object identifier');
			$t->columnWithSpec('iri', DBType::VARCHAR, 128, DBCol::NOT_NULL, null, 'IRI relating to this user');
			$t->indexWithSpec('uuid', DBIndex::INDEX, 'uuid');
			$t->indexWithSpec('iri', DBIndex::INDEX, 'iri');
			return $t->apply();
		}
	}
}
