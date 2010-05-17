<?php

/*
 * Copyright 2010 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/model.php');

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
		if(!$this->db)
		{
			echo "Warning: skipping setup of " . $this->moduleId . " because it has no database\n";
			return true;
		}
		$currentVersion = $this->db->schema->moduleVersion($this->moduleId);
		while($currentVersion < $this->latestVersion)
		{
			echo " -> Updating " . $this->moduleId . " from " . $currentVersion . " to " . ($currentVersion + 1) . "\n";
			do
			{
				$this->db->begin();
				$currentVersion = $this->db->schema->moduleVersion($this->moduleId);
				if($currentVersion >= $this->latestVersion)
				{
					$this->db->rollback();
					return true;
				}
				$result = $this->updateSchema($currentVersion + 1);
				if($result == false)
				{
					echo "*** Update of " . $this->moduleId . " from " . $currentVersion . " to " . ($currentVersion + 1) . " failed\n";
					return false;
				}
				if(is_string($result))
				{
					$comment = $result;
				}
				else
				{
					$comment = null;
				}
				$this->db->schema->setModuleVersion($this->moduleId, $currentVersion + 1, $comment);
			}
			while(!$this->db->commit());
			$currentVersion++;
		}
		return true;
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
