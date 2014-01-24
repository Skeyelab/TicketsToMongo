<?php
date_default_timezone_set('America/Chicago');

require ('vendor/autoload.php');

use Jasny\MySQL\DB;
use Sly\PushOver\Model\Push;
use Sly\PushOver\PushManager;
use Simplon\Config\Config;

$config = Config::getInstance()->setConfigPath(__DIR__ . '/config.php');
$DBConfig = $config->getConfigByKeys(['database', 'mysql']);


//configure MySQL connection
$csb = new DB($DBConfig["server"], $DBConfig["username"], $DBConfig["password"], $DBConfig["database"], $DBConfig["port"]);

$items = $csb->fetchAll("SELECT * FROM `zendeskulator_desks`;");

$desks = array();
foreach ($items as $item) {
	$desks[$item["domain"]] = $item;
}

//configure pheanstalk
$pheanstalk = new Pheanstalk_Pheanstalk($config->getConfigByKeys(['beanstalkd', 'server']));
$pheanstalk->watch('audits-mongo-test');
$pheanstalk->ignore('default');
$pheanstalk->useTube('audits-mongo-test');



//config mongodb connection
//$m = new MongoClient("mongodb://cs-ops-mac-mini.group.on:27016");
$m = new MongoClient();

// select a database
$db = $m->Zendesk;
// select a collection (analogous to a relational database's table)
$collection = $db->Tickets;

$pushManager = new PushManager('uurwWV2RzVq9YJBi2ozg6KJRqhxVVh', 'ahxqMWVtsQmLTmhL8kbSGfwBViufat');


