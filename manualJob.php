#!/usr/local/php5/bin/php
<?php
ini_set('memory_limit', '85M');
require 'bootstrap.php';
$ticket = cli\prompt("Ticket", $default = false, $marker = ':');

		$pheanstalk->putInTube('audits-mongo-test','{"id":'.$ticket.',"account":"support.groupon.com"}',1) ;
