<?php

/**
 * @file examples/mimetest.php
 * @brief Sample demonstrating the MIME class
 */

require_once(dirname(__FILE__) . '/../lib/common.php');

uses('mime');

$extensions = array('.txt', 'rtf', 'html', 'mp4');

foreach($extensions as $ext)
{
	$mime = MIME::typeForExt($ext);
	$desc = MIME::description($mime);
	echo sprintf("Extension: %-6s  MIME: %-25s  Description: %s\n", $ext, $mime, $desc);
}

echo str_repeat('-', 80) . "\n";
$types = array('image/gif', 'video/mp4', 'application/rdf+xml', 'text/plain', 'application/x-unknown');

foreach($types as $mime)
{
	$ext = MIME::extForType($mime);
	$desc = MIME::description($mime);
	echo sprintf("Extension: %-6s  MIME: %-25s  Description: %s\n", $ext, $mime, $desc);
}
