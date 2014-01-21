#!/usr/bin/env php
<?php

include("bootstrap.php");
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$start = time();

$job = $pheanstalk->reserve();
//$cb = new Couchbase("127.0.0.1:8091", "", "", "tickets");

$log = new Logger('testcomments.php');
$log->pushHandler(new StreamHandler(__DIR__ . '/log/data.log', Logger::WARNING));


//echo $job->getData();

do {

	$jobObj = json_decode($job->getData());

	echo "Starting:". $jobObj->id." | ";

	echo "Audits: ";

	$user = $desks[$jobObj->account]['api_user'];
	$pass = $desks[$jobObj->account]['api_key'];
	b:
	$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'/audits.json?include=tickets')
	->authenticateWith($user.'/token', $pass)
	// ->sendsJson()
	->expectsJson()
	->send();

	if ($response->code ==429) {
		$myPush = new Push();
		$myPush->setMessage('audits');
		$myPush->setTitle('429'); // Optional
		$pushManager->push($myPush);

		$log->addWarning('Error 429: Line 33');

		echo "Waiting ".$response->headers["Retry-After"]." seconds".PHP_EOL;
		sleep($response->headers["Retry-After"]);
		goto b;
	}
	elseif ($response->code == 404) {
		$log->addWarning('Error 404: Line 43');

		$pheanstalk->delete($job);
		goto c;
	}
	elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die();
	}

	echo "Fetched, ";

	$MongoTicket = (object) $response->body->tickets[0];


	$MongoTicket->created_at = new MongoDate(strtotime($MongoTicket->created_at));
	$MongoTicket->updated_at = new MongoDate(strtotime($MongoTicket->updated_at));

	unset($MongoTicket->fields);

	foreach ($response->body->audits as $key=>$audit) {
		$response->body->audits[$key]->created_at = new MongoDate(strtotime($response->body->audits[$key]->created_at));

	}
	$MongoTicket->_id = $MongoTicket->id;

	$MongoTicket->audits = $response->body->audits;
	echo "Compiled | ";
	echo "Comments: ";

	d:
	unset($response);
	$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'/comments.json')
	->authenticateWith($user.'/token', $pass)
	// ->sendsJson()
	->expectsJson()
	->send();

	if ($response->code ==429) {
		$myPush = new Push();
		$myPush->setMessage('comments');
		$myPush->setTitle('429'); // Optional
		$pushManager->push($myPush);

		$log->addWarning('Error 429: Line 74');

		echo "Waiting ".$response->headers["Retry-After"]." seconds".PHP_EOL;
		sleep($response->headers["Retry-After"]);
		goto d;
	}
	elseif ($response->code == 404) {
		$pheanstalk->delete($job);
		// echo "*";

		goto c;
	}
	elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die();
	}
	echo "Fetched, ";

	foreach ($response->body->comments as $key=>$comment) {
		$response->body->comments[$key]->created_at = new MongoDate(strtotime($response->body->comments[$key]->created_at));
	}

	$MongoComments = (object) $response->body->comments;

	$MongoTicket->comments = $MongoComments;
	echo "Compiled | ";
	echo "Metrics: ";
	e:
	unset($response);
	$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'/metrics.json')
	->authenticateWith($user.'/token', $pass)
	// ->sendsJson()
	->expectsJson()
	->send();

	if ($response->code ==429) {
		$myPush = new Push();
		$myPush->setMessage('comments');
		$myPush->setTitle('429'); // Optional
		$pushManager->push($myPush);

		$log->addWarning('Error 429: Line 74');

		echo "Waiting ".$response->headers["Retry-After"]." seconds".PHP_EOL;
		sleep($response->headers["Retry-After"]);
		goto e;
	}
	elseif ($response->code == 404) {
		$pheanstalk->delete($job);
		// echo "*";

		goto c;
	}
	elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die();
	}
	echo "Fetched, ";

	unset($response->body->ticket_metric-> url);
	unset($response->body->ticket_metric-> id);
	unset($response->body->ticket_metric-> ticket_id);
	unset($response->body->ticket_metric-> created_at);
	unset($response->body->ticket_metric-> updated_at);

	foreach ( $response->body->ticket_metric as $key=>$metric) {
		if (isset($metric)) {
			if (strpos($key,'_at') !== false) {
				$metric = new MongoDate(strtotime($metric));
			}
			$MongoTicket -> $key = $metric;
		}
	}



	echo "Compiled | ";

	if ($collection->save($MongoTicket)) {
		echo "Saved".PHP_EOL;


		//  $cb->set($MongoTicket->id, json_encode($MongoTicket) );

		$pheanstalk->delete($job);

	}
	else {
		echo "Not saved".PHP_EOL;
	}

	c:

	if (time()-$start >= 600) {
		die();
	}

}
while ( $job = $pheanstalk->reserve());








?>
