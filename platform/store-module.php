<?php

/* Eregansu: Complex object store
 *
 * Copyright 2010 Mo McRoberts.
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

class StoreModule extends Module
{
	/* The name of the 'objects' table */
	protected $objects = 'object';
	protected $objects_base = 'object_base';
	protected $objects_iri = 'object_iri';
	protected $objects_tags = 'object_tags';
	protected $objects_data = 'object_data';
	
	public $moduleId = 'com.nexgenta.eregansu.store';
	public $latestVersion = 9;
	public $standalone = false;

	public static function getInstance($args = null)
	{	
		if(!isset($args['class'])) $args['class'] = 'StoreModule';
		return parent::getInstance($args);
	}
	
	public function __construct($args)
	{
		if(isset($args['objectsTable'])) $this->objects = $args['objectsTable'];
		if(isset($args['objectsBaseTable'])) $this->objects_base = $args['objectsBaseTable'];
		if(isset($args['objectsIriTable'])) $this->objects_base = $args['objectsIrisTable'];
		if(isset($args['objectsTagsTable'])) $this->objects_base = $args['objectsTagsTable'];
		parent::__construct($args);		
	}
	
	public function updateSchema($targetVersion)
	{
		if($targetVersion == 1)
		{
			$t = $this->db->schema->tableWithOptions($this->objects, DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Unique object identifier (UUID)');
			$t->columnWithSpec('data', DBType::TEXT, null, DBCol::NULLS|DBCol::BIG, null, 'JSON-encoded serialised object data');
			$t->columnWithSpec('created', DBType::DATETIME, null, DBCol::NOT_NULL, null, 'Timestamp of the object being created');
			$t->columnWithSpec('creator_scheme', DBType::VARCHAR, 16, DBCol::NULLS, null, 'Scheme of the creating user');
			$t->columnWithSpec('creator_uuid', DBType::UUID, null, DBCol::NULLS, null, 'UUID of the creating user');
			$t->columnWithSpec('modified', DBType::DATETIME, null, DBCol::NOT_NULL, null, 'Timestamp of the object being last modified');
			$t->columnWithSpec('modifier_scheme', DBType::VARCHAR, 16, DBCol::NULLS, null, 'Scheme of the modifying user');
			$t->columnWithSpec('modifier_uuid', DBType::UUID, null, DBCol::NULLS, null, 'UUID of the modifying user');
			$t->columnWithSpec('owner', DBType::VARCHAR, 64, DBCol::NULLS, null, 'Identifier of the key used to sign the last update to this object');
			$t->columnWithSpec('dirty', DBType::BOOLEAN, null, DBCol::NOT_NULL, 'Y', 'Whether the object needs to be re-indexed on the next pass');
			$t->indexWithSpec(null, DBIndex::PRIMARY, 'uuid');
			$t->indexWithSpec('creator_scheme', DBIndex::INDEX, 'creator_scheme');
			$t->indexWithSpec('creator_uuid', DBIndex::INDEX, 'creator_uuid');
			$t->indexWithSpec('modifier_scheme', DBIndex::INDEX, 'modifier_scheme');
			$t->indexWithSpec('modifier_uuid', DBIndex::INDEX, 'modifier_uuid');
			$t->indexWithSpec('dirty', DBIndex::INDEX, 'dirty');
			$t->indexWithSpec('owner', DBIndex::INDEX, 'owner');
			return $t->apply();
		}
		if($targetVersion == 2)
		{
			$t = $this->db->schema->tableWithOptions($this->objects_base, DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Object identifier');
			$t->columnWithSpec('kind', DBType::VARCHAR, 36, DBCol::NULLS, null, 'Object kind');
			$t->columnWithSpec('tag', DBType::VARCHAR, 64, DBCol::NULLS, null, 'Short object name');
			$t->columnWithSpec('realm', DBType::UUID, null, DBCol::NULLS, null, 'Associated realm identifier, if any');
			$t->indexWithSpec(null, DBIndex::PRIMARY, 'uuid');
			$t->indexWithSpec('kind', DBIndex::INDEX, 'kind');
			$t->indexWithSpec('tag', DBIndex::INDEX, 'tag');
			$t->indexWithSpec('realm', DBIndex::INDEX, 'realm');
			return $t->apply();
		}
		if($targetVersion == 3)
		{
			$t = $this->db->schema->tableWithOptions($this->objects_tags, DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Object identifier');
			$t->columnWithSpec('tag', DBType::VARCHAR, 64, DBCol::NOT_NULL, null, 'Tag');
			$t->indexWithSpec('uuid', DBIndex::INDEX, 'uuid');
			$t->indexWithSpec('tag', DBIndex::INDEX, 'tag');
			return $t->apply();
		}
		if($targetVersion == 4)
		{
			$t = $this->db->schema->tableWithOptions($this->objects_iri, DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Object identifier');
			$t->columnWithSpec('iri', DBType::VARCHAR, 128, DBCol::NULLS, null, 'IRI of this object');
			$t->indexWithSpec('uuid', DBIndex::INDEX, 'uuid');
			$t->indexWithSpec('iri', DBIndex::INDEX, 'iri');
			return $t->apply();
		}
		if($targetVersion == 5)
		{
			$t = $this->tableWithOptions($this->objects . '_tmp', DBTable::CREATE_ALWAYS);
			$t->columnWithSpec('uuid', DBType::UUID, null, DBCol::NOT_NULL, null, 'Unique object identifier (UUID)');
			$t->columnWithSpec('created', DBType::DATETIME, null, DBCol::NOT_NULL, null, 'Timestamp of the object being created');
			$t->columnWithSpec('creator_scheme', DBType::VARCHAR, 16, DBCol::NULLS, null, 'Scheme of the creating user');
			$t->columnWithSpec('creator_uuid', DBType::UUID, null, DBCol::NULLS, null, 'UUID of the creating user');
			$t->columnWithSpec('modified', DBType::DATETIME, null, DBCol::NOT_NULL, null, 'Timestamp of the object being last modified');
			$t->columnWithSpec('modifier_scheme', DBType::VARCHAR, 16, DBCol::NULLS, null, 'Scheme of the modifying user');
			$t->columnWithSpec('modifier_uuid', DBType::UUID, null, DBCol::NULLS, null, 'UUID of the modifying user');
			$t->columnWithSpec('owner', DBType::VARCHAR, 64, DBCol::NULLS, null, 'Identifier of the key used to sign the last update to this object');
			$t->columnWithSpec('dirty', DBType::BOOLEAN, null, DBCol::NOT_NULL, 'Y', 'Whether the object needs to be re-indexed on the next pass');
			$t->indexWithSpec(null, DBIndex::PRIMARY, 'uuid');
			$t->indexWithSpec('creator_scheme', DBIndex::INDEX, 'creator_scheme');
			$t->indexWithSpec('creator_uuid', DBIndex::INDEX, 'creator_uuid');
			$t->indexWithSpec('modifier_scheme', DBIndex::INDEX, 'modifier_scheme');
			$t->indexWithSpec('modifier_uuid', DBIndex::INDEX, 'modifier_uuid');
			$t->indexWithSpec('dirty', DBIndex::INDEX, 'dirty');
			$t->indexWithSpec('owner', DBIndex::INDEX, 'owner');
			return $t->apply();
		}
		if($targetVersion == 6)
		{
			$rs = $this->db->query('SELECT "uuid", "created", "creator_scheme", "creator_uuid", "modified", "modifier_scheme", "modifier_uuid", "owner", "dirty" FROM {' . $this->objects . '}');
			while(($row = $rs->next()))
			{
				$this->db->insert($this->objects . '_tmp', $row);
			}
			return true;
		}
		if($targetVersion == 7)
		{
			return $this->schema->renameTable($this->objects, $this->objects_data);
		}
		if($targetVersion == 8)
		{
			return $this->schema->renameTable($this->objects . '_tmp', $this->objects);
		}
		if($targetVersion == 9)
		{
			$t = $this->table($this->objects_data);
			$t->dropColumn('created');
			$t->dropColumn('creator_scheme');
			$t->dropColumn('creator_uuid');
			$t->dropColumn('modified');
			$t->dropColumn('modifier_scheme');
			$t->dropColumn('modifier_uuid');
			$t->dropColumn('owner');
			$t->dropColumn('dirty');
			return $t->apply();
		}
		return false;
	}
}
