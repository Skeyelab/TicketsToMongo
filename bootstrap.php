<?php
date_default_timezone_set('America/Chicago');

require 'vendor/autoload.php';

use Jasny\MySQL\DB;
use Sly\PushOver\PushManager;
use Simplon\Config\Config;

//setup airbrake.io
//Airbrake\EventHandler::start('4abe80271b59c5b1c9af0a4e46449349');

$apiKey  = '4abe80271b59c5b1c9af0a4e46449349'; // This is required
$ABoptions = array(); // This is optional

$ABconfig = new Airbrake\Configuration($apiKey, $ABoptions);
$ABclient = new Airbrake\Client($ABconfig);

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

error_reporting(E_ALL);
/* Configure logging */
MongoLog::setLevel(MongoLog::WARNING);
MongoLog::setModule(MongoLog::RS);

//config mongodb connection
    $m = new MongoClient("mongodb://erics-mac-mini.group.on,cs-ops-mac-mini.group.on,breadcrumbsmini.group.on/?replicaSet=rs0");
$m->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED, array());
$pushManager = new PushManager('uurwWV2RzVq9YJBi2ozg6KJRqhxVVh', 'ahxqMWVtsQmLTmhL8kbSGfwBViufat');
