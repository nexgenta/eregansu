<?php

/* Copyright 2010-2011 Mo McRoberts.
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

abstract class XMLNS
{
	const xml = 'http://www.w3.org/XML/1998/namespace';
	const xmlns = 'http://www.w3.org/2000/xmlns/';
	const xhtml = 'http://www.w3.org/1999/xhtml';
	const dc = 'http://purl.org/dc/elements/1.1/';
	const dcterms = 'http://purl.org/dc/terms/';
	const xsd = 'http://www.w3.org/2001/XMLSchema#';
	const rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
	const rdfs = 'http://www.w3.org/2000/01/rdf-schema#';
	const owl = 'http://www.w3.org/2002/07/owl#';
	const foaf = 'http://xmlns.com/foaf/0.1/';
	const skos = 'http://www.w3.org/2008/05/skos#';
	const time = 'http://www.w3.org/2006/time#';
	const rdfg = 'http://www.w3.org/2004/03/trix/rdfg-1/';
	const event = 'http://purl.org/NET/c4dm/event.owl#';
	const frbr = 'http://purl.org/vocab/frbr/core#';
	const dcmit = 'http://purl.org/dc/dcmitype/';
	const geo = 'http://www.w3.org/2003/01/geo/wgs84_pos#';
	const mo = 'http://purl.org/ontology/mo/';
	const theatre = 'http://purl.org/theatre#';
	const participation = 'http://purl.org/vocab/participation/schema#';
	const xhv = 'http://www.w3.org/1999/xhtml/vocab#';
	const gn = 'http://www.geonames.org/ontology#';
	const exif = 'http://www.kanzaki.com/ns/exif#';

	public static function fqname($namespace, $local = null)
	{
		if($local == null)
		{
			if(is_object($namespace))
			{
				$local = $namespace->localName;
				$namespace = $namespace->namespaceURI;
			}
			else
			{
				return $namespace;
			}
		}
		$t = substr($namespace, -1);
		if(!ctype_alnum($t))
		{
			return $namespace . $local;
		}
		return $namespace . ' ' . $local;
	}
}
