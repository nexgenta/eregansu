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
	array('kind' => 'dns', 'name' => 'example.com', 'expect' => '9073926b-929f-31c2-abc9-fad77ae3e8eb'),
	array('kind' => 'dns', 'name' => 'nexgenta.com', 'expect' => 'a9b7317b-ff19-3e2f-9dba-3abb3821a3e0'),
	array('kind' => 'url', 'name' => 'http://github.com/nexgenta/eregansu', 'expect' => '5d8a627e-9e4c-390d-a99b-d01f071d9162'),
	array('kind' => 'oid', 'name' => '1.3.6.1.1.1.2.0', 'expect' => 'e2fb3d23-a44e-37f0-a556-beac4ddad7bf'),
	array('kind' => 'dn', 'name' => '/C=GB/L=Glasgow/O=Example/', 'expect' => 'e8a1c0bd-445f-3790-9fc1-1bddd7a7c958'),
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
	$uu = UUID::generate(UUID::HASH_MD5, $ns, $t['name']);
	if(strcasecmp($uu, $t['expect']))
	{
		echo $t['kind'] . '(' . $t['name'] . '): expected ' . $t['expect'] . ', generated ' . $uu . "\n";
		exit(1);
	}
}
exit(0);

