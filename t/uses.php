<?php

/* Copyright 2011 Mo McRoberts.
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

class TestUses extends TestHarness
{
	public function main()
	{
		uses('harness', 'url', 'rdf');
		
		if(!class_exists('URL'))
		{
			echo "The URL class does not exist\n";
			return;
		}
		if(!class_exists('RDFDocument'))
		{
			echo "The RDFDocument class does not exist\n";
			return;
		}
		return true;
	}
}

return 'TestUses';
