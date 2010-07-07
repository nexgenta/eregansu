<?php

/* This sample script attempts to continuously query a database. The
 * defaults are such that in the event of a transient (network-related)
 * issue, the connection will be automatically re-established, logging
 * to stderr at start, completition and periodically in between. When
 * running with a SAPI other than 'cli', the defaults are different,
 * but in both cases can be customised with options in the connection
 * URI.
 */

require_once(dirname(__FILE__) . '/../lib/common.php');

uses('db');

$conn = DBCore::connect('mysql://localhost/');

echo "Connection established\n";

while(true)
{
	$rs = $conn->rows('SHOW DATABASES');
	sleep(1);
}
