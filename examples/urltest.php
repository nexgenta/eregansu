<?php

require_once(dirname(__FILE__) . '/../lib/common.php');

uses('url');

$u = $base = null;

if(isset($argv[1])) $u = $argv[1];
if(isset($argv[2])) $base = $argv[2];

$url = new URL($u, $base);
print_r($url);
echo strval($url) . "\n";
