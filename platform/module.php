<?php

/* Auto-configuring database schema modules
 *
 * A “module” is essentially a class which, rather than
 * being responsible for storing and retrieving data in a
 * database, instead is concerned with managing the
 * structure of the database.
 *
 * Updates to the database schema are versioned, with the
 * module class specifying the current latest version number
 * and the incremental steps in how to get there.
 *
 * The implementation below does the heavy lifting of
 * comparing the version number in the database itself with
 * the module’s version number and invoking the descendant
 * schema update code in order to perform the necessary
 * incremental updates.
 */

abstract class Module extends Model
{
	/* The current latest version number for this module. If zero,
	 * no updates will be performed.
	 */
	public $latestVersion = 0;
	/* The module identifier: a reverse-DNS-style identifier for
	 * the module. For example, com.example.mymodule
	 */
	public $moduleId = null;
	
	/* Compare the stored version number in the database (if any)
	 * with $this->latestVersion. Cycle from the former to the
	 * latter, calling $this->updateSchema() as required.
	 */
	public function setup()
	{
		do
		{
			break; /* XXX, obviously */
		}
		while($currentVersion < $this->latestVersion);
	}
	
	/* Perform a single incremental schema update. Return true
	 * if successful, or false if an unrecoverable error occurs
	 * which should abort the update process.
	 *
	 * The initial version is 1 (i.e., $targetVersion == 1) and
	 * will increment by one up to $this->latestVersion.
	 *
	 * (In other words, if $this->latestVersion is zero, this
	 * method will never be called).
	 */
	public function updateSchema($targetVersion)
	{
	
	}
}
