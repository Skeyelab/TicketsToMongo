#!/usr/bin/env php
<?php
date_default_timezone_set('America/Chicago');

for ($t=0;$t<3;$t++) {
	echo PHP_EOL;
}

include 'bootstrap.php';
newrelic_background_job ();

$skipThese = array();

$start = time();

$job = $pheanstalk->reserve();

//$cb = new Couchbase("127.0.0.1:8091", "", "", "tickets");

//echo $job->getData();

do {
	//var_dump($pheanstalk->peek($pheanstalk->peekDelayed()->getId()));
	$delay = 3;
	$diff = 3;

	$jobObj = json_decode($job->getData());

	if ($jobObj->account == "support.groupon.com") {
		$jobObj->account = "groupon.zendesk.com";
	}

	if (strpos($jobObj->account,"Liquid error") !== false ) {
		$pheanstalk->delete($job);
		goto c;

	}

	echo "Starting: ". $jobObj->account." : ".$jobObj->id." | ";

	if (in_array($jobObj->account, $skipThese)) {

		$found = array_search($jobObj->account, $skipThese);
		$diff =  $found - time();
		$diff = abs($diff);
		echo "Skipping for $diff".PHP_EOL;

		if ($diff <= 1) {
			unset ($skipThese[$found]);
			$diff = 3;
		}

		$pheanstalk->release($job, 2500,abs($diff));
		goto c;
	}

	echo "Audits: ";

	if ($jobObj->account == "kontakt.groupon.at" || $jobObj->account == "kontakt.groupon.ch") {
		$pheanstalk->delete($job);
		goto c;
	}

	$user = $desks[$jobObj->account]['api_user'];
	$pass = $desks[$jobObj->account]['api_key'];
	b:
	$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'/audits.json?include=tickets')
	->authenticateWith($user.'/token', $pass)
	// ->sendsJson()
	//->expectsJson()
	->send();

	print_r($response->code);

	if ($response->code ==429) {
		$delay = $response->headers["Retry-After"];

		$skipThese[time() + $delay] = $jobObj->account;
		$pheanstalk->release($job, 2500, abs($delay));
		echo " Releasing ".PHP_EOL;

		//  echo " Waiting: ".$response->headers["Retry-After"].PHP_EOL;
		//  sleep($response->headers["Retry-After"]);
		goto c;
	} elseif ($response->code == 404) {
		$dbName = str_replace(".", "-", $jobObj->account) ;
		$db = $m-> $dbName;
		// var_dump($db);die(PHP_EOL);
		$collection = $db->tickets;

		$newdata = array('$set' => array("status" => "deleted"));
		$collection->update(array("id"=>$jobObj->id),$newdata);
		echo PHP_EOL;
		$pheanstalk->delete($job);
		goto c;
	} elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die(PHP_EOL);
	}

	if (isset($response->body->tickets[0])) {
		$MongoTicket = (object) $response->body->tickets[0];
		// var_dump($MongoTicket);
		echo " Fetched, ";
		foreach ($response->body->audits as $key=>$audit) {
			$response->body->audits[$key]->created_at = new MongoDate(strtotime($response->body->audits[$key]->created_at));

		}
		$MongoTicket->audits = $response->body->audits;

	} else {

		echo "No Audits. Trying Ticket API: ";
		f:
		$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'.json')
		->authenticateWith($user.'/token', $pass)
		// ->sendsJson()
		//  ->expectsJson()
		->send();
		print_r($response->code);

		if ($response->code ==429) {
			$delay = $response->headers["Retry-After"];

			$skipThese[time() + $delay] = $jobObj->account;
			$pheanstalk->release($job, 2500, abs($delay));
			echo " Releasing ".PHP_EOL;
			goto c;
		} elseif ($response->code == 404) {
			$log->addWarning('Error 404: Line 43');
			echo PHP_EOL;

			goto c;
		} elseif ($response->code != 200) {
			echo "code: ".$response->code;
			var_dump($jobObj);die(PHP_EOL);
		}

		$MongoTicket = (object) $response->body->ticket;
		echo " Fetched, ";

	}

	// $MongoTicket->_id = $MongoTicket->id;

	$MongoTicket->created_at = new MongoDate(strtotime($MongoTicket->created_at));
	$MongoTicket->updated_at = new MongoDate(strtotime($MongoTicket->updated_at));
	unset($MongoTicket->fields);

	echo "Compiled | ";

	// die(PHP_EOL);

	echo "Comments: ";

	d:
	unset($response);
	$response = \Httpful\Request::get('https://'.$jobObj->account.'/api/v2/tickets/'.$jobObj->id.'/comments.json')
	->authenticateWith($user.'/token', $pass)
	// ->sendsJson()
	// ->expectsJson()
	->send();
	print_r($response->code);

	if ($response->code ==429) {
		$delay = $response->headers["Retry-After"];
		$pheanstalk->release($job, 2500, abs($delay));
		echo " Releasing ".PHP_EOL;
		$skipThese[time() + $response->headers["Retry-After"]] = $jobObj->account;

		//  echo " Waiting: ".$response->headers["Retry-After"].PHP_EOL;
		//  sleep($response->headers["Retry-After"]);
		goto c;
	} elseif ($response->code == 404) {
		$pheanstalk->delete($job);
		// echo "*";
		echo PHP_EOL;

		goto c;
	} elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die(PHP_EOL);
	}
	echo " Fetched, ";

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
	// ->expectsJson()
	->send();
	print_r($response->code);

	if ($response->code ==429) {
		$delay = $response->headers["Retry-After"];
		$pheanstalk->release($job, 2500, abs($delay));
		echo " Releasing ".PHP_EOL;

		$skipThese[time() + $response->headers["Retry-After"]] = $jobObj->account;
		//  echo " Waiting: ".$response->headers["Retry-After"].PHP_EOL;
		//  sleep($response->headers["Retry-After"]);
		goto c;
	} elseif ($response->code == 404) {
		//$pheanstalk->delete($job);
		echo "No Metrics. Skipped ";
		echo PHP_EOL;

		goto g;
	} elseif ($response->code != 200) {
		echo "code: ".$response->code;
		var_dump($jobObj);die(PHP_EOL);
	}
	echo " Fetched, ";

	unset($response->body->ticket_metric-> url);
	unset($response->body->ticket_metric-> id);
	unset($response->body->ticket_metric-> ticket_id);
	unset($response->body->ticket_metric-> created_at);
	unset($response->body->ticket_metric-> updated_at);

	foreach ($response->body->ticket_metric as $key=>$metric) {
		if (isset($metric)) {
			if (strpos($key,'_at') !== false) {
				$metric = new MongoDate(strtotime($metric));
			}
			$MongoTicket -> $key = $metric;
		}
	}

	echo "Compiled | ";
	g:

	// settype($jobObj->account, "string");

	$dbName = str_replace(".", "-", $jobObj->account) ;
	$db = $m-> $dbName;
	// var_dump($db);die(PHP_EOL);
	$collection = $db->tickets;

	z:
	try {
		$qwe = $collection->update(array("id"=>$MongoTicket->id),$MongoTicket, array("upsert"=>true));
	} catch (MongoCursorException $e) {
		$ABclient->notifyOnException($e);
		echo ".";
		sleep(10);
		goto z;
	}

	//var_dump($qwe);die(PHP_EOL);
	if ($qwe) {
		echo "Saved".PHP_EOL;

		//  $cb->set($MongoTicket->id, json_encode($MongoTicket) );

		$pheanstalk->delete($job);

	} else {
		echo "Not saved".PHP_EOL;
	}

	c:

	if (time()-$start >= 600) {
		die(PHP_EOL);
	}

} while ( $job = $pheanstalk->reserve());

?>
