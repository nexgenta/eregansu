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

require_once(dirname(__FILE__) . '/../lib/common.php');

uses('uuid');

$tests = array(
	array('kind' => 'dns', 'name' => 'example.com', 'expect' => 'cfbff0d1-9375-5685-968c-48ce8b15ae17'),
	array('kind' => 'dns', 'name' => 'nexgenta.com', 'expect' => '3a5fb16b-2186-5e0f-a8a6-9e7c5467143e'),
	array('kind' => 'url', 'name' => 'http://github.com/nexgenta/eregansu', 'expect' => 'f2edc71e-06a9-5ce6-bfcf-4e0622eca470'),
	array('kind' => 'oid', 'name' => '1.3.6.1.1.1.2.0', 'expect' => 'a89fa81a-c451-57e2-92ae-e1baf89482d7'),
	array('kind' => 'dn', 'name' => '/C=GB/L=Glasgow/O=Example/', 'expect' => 'bf85d135-22d7-539f-a599-793bc7eb2529'),
	);

foreach($tests as $t)
{
	$ns = null;
	switch($t['kind'])
	{
	case 'dns':
		$ns = UUID::DNS;
		break;
	case 'url':
		$ns = UUID::URL;
		break;
	case 'oid':
		$ns = UUID::OID;
		break;
	case 'dn':
		$ns = UUID::DN;
		break;
	}
	$uu = UUID::generate(UUID::HASH_SHA1, $ns, $t['name']);
	if(strcasecmp($uu, $t['expect']))
	{
		echo $t['kind'] . '(' . $t['name'] . '): expected ' . $t['expect'] . ', generated ' . $uu . "\n";
		exit(1);
	}
}
exit(0);

