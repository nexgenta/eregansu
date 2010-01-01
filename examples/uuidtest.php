<?php

require_once(dirname(__FILE__) . '/../lib/common.php');

uses('uuid');

$uuid = UUID::generate();

echo "Generated a new UUID:       $uuid\n";

$nil = UUID::nil();

echo "Generated the NULL UUID:    $nil\n";

$uuid = 'AFF333C4-E642-4A02-9E25-6440706DBB1A';
echo "Pre-defined UUID #1:        $uuid\n";
$info = UUID::parse($uuid);
print_r($info);
$uuid = UUID::unparse($info);
echo "Unparsed UUID #1:           $uuid\n";

$uuid = 'd7aa0552-f717-11de-848e-aa0004000104';
echo "Pre-defined UUID #2:        $uuid\n";
$info = UUID::parse($uuid);
print_r($info);
$uuid = UUID::unparse($info);
echo "Unparsed UUID #2:           $uuid\n";
$iri = UUID::iri($uuid);
echo "UUID #2 as an IRI:          $iri\n";
