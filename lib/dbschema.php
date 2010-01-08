<?php

abstract class DBSchema
{
	public $db; /* Associated database connection */
	
	protected $tableClass; /* Name of descendant of DBTable */
	
	public static function schemaForConnection($connection)
	{
		switch($connection->dbms)
		{
			case 'mysql':
				require_once(dirname(__FILE__) . '/mysql-schema.php');
				return new MySQLSchema($connection);
		}
		return null;
	}
	
	public function __construct($connection)
	{
		$this->db = $connection;
	}
	
	/* Return an existing table */
	public function table($name)
	{
		return $this->tableWithOptions($name, DBTable::EXISTING);
	}
	
	/* Return an existing table or create a new one */
	public function tableWithOptions($name, $options)
	{
		$c = $this->tableClass;
		return new $c($this, $name, $options);
	}
}

abstract class DBTable
{
	/* Flags for DBSchema::tableWithOptions */
	const EXISTING = 0; /* Don't create the table: retrieve existing spec */
	const CREATE_IF_NEEDED = 1; /* Create it if it doesn't exist; no-op otherwise */
	const CREATE_ALWAYS = 2; /* Always create, dropping if necessary */
	
	/* Column types */
	const CHAR = 1;
	const INT = 2;
	const VARCHAR = 3;
	const DATE = 4;
	const DATETIME = 5;
	const ENUM = 6;
	const SET = 7;
	const SERIAL = 8;
	
	/* Column flags */
	const NULLS = 0;
	const NOT_NULL = 1;
	const UNSIGNED = 2;

	public $schema;
	public $name;
	public $options;

	protected $columns = array();
	protected $indices = array();
	protected $changes = array();
	protected $nativeCreateOptions = '';
	
	public function __construct($schema, $name, $options)
	{
		$this->schema = $schema;
		$this->name = $name;
		$this->options = $options;
		
		if($options == self::EXISTING)
		{
			$this->retrieve();
		}
	}
	
	public function apply()
	{
		if($this->options == self::EXISTING)
		{
			if(count($this->changes))
			{
				return $this->applyChanges();
			}
			return true;
		}
		$this->createTable();
	}
	
	public function column($name)
	{
		if(isset($this->columns[$name])) return $this->columns[$name];
		return null;
	}
	
	public function columnWithSpec($name, $type, $sizeValues, $flags = null, $defaultValue = null, $comment = null)
	{
		if($flags == null) $flags = self::NULLS;
		$info = array(
			'name' => $name,
			'type' => $type,
			'sizeValues' => $sizeValues,
			'flags' => $flags,
			'default' => $defaultValue,
			'comment' => $comment,
		);
		if(!$this->nativeColumnSpec($info))
		{
			return false;
		}
		if($this->options == self::EXISTING)
		{
			if(isset($this->columns[$name]))
			{
				$this->changes[] = $this->replaceColumn($info);
			}
			else
			{
				$this->changes[] = $this->addColumn($info);
			}
		}
		$this->columns[$name] = $info;
	}
	
	/* Set $info['spec'] to be a native column specification string
	 * for the given specification parameters, suitable for use in
	 * CREATE TABLE and ALTER TABLE statements.
	 *
	 * e.g., "foo" VARCHAR(32) NOT NULL DEFAULT 'Bar' COMMENT 'A dummy column'
	 */
	abstract protected function nativeColumnSpec(&$info);
	
	/* Return a string containing an ALTER TABLE statement for replacing
	 * an existing column with a new specification.
	 *
	 * If the previous column name differs from the new one, then
	 * $info['previous'] will be set to the old name.
	 */
	protected function replaceColumn($info)
	{
		if(isset($info['previous']))
		{
			return 'ALTER TABLE "' . $this->name . '" CHANGE COLUMN "' . $info['previous'] . '" ' . $info['spec'];
		}
		return 'ALTER TABLE "' . $this->name . '" MODIFY COLUMN ' . $info['spec'];
	}
	
	/* Return a string containing an ALTER TABLE statement for adding
	 * a new column.
	 */
	protected function addColumn($info)
	{
		return 'ALTER TABLE "' . $this->name . '" ADD COLUMN ' . $info['spec'];
	}
	
	/* Execute a CREATE TABLE statement based on the content of
	 * $this->columns and $this->indicies.
	 */
	protected function createTable()
	{
		$drop = 'DROP TABLE IF EXISTS "' . $this->name . '"';
		$cl = array();
		if($this->options == SELF::CREATE_IF_NEEDED)
		{
			$create = 'CREATE TABLE IF NOT EXISTS "' . $this->name . '" (';
		}
		else
		{
			$create = 'CREATE TABLE "' . $this->name . '" (';		
		}
		foreach($this->columns as $col)
		{
			$cl[] = $col['spec'];
		}
		foreach($this->indices as $index)
		{
			$cl[] = $index['spec'];
		}
		$create .= implode(', ', $cl) . ')' . $this->nativeCreateOptions;
		do
		{
			$this->schema->db->begin();
			if($this->options == self::CREATE_ALWAYS)
			{
				$this->schema->db->exec($drop);
			}
			$this->schema->db->exec($create);		
		}
		while(!$this->schema->db->commit());
		return true;
	}
	
	/* Execute a sequence of ALTER TABLE statements contained within
	 * $this->changes
	 */
	protected function applyChanges()
	{
		do
		{
			$this->schema->db->begin();
			foreach($this->changes as $change)	
			{
				$this->schema->db->exec($change);
			}
		}
		while(!$this->db->commit());
		return true;
	}
	
}