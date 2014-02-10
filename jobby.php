#!/usr/bin/env php
<?php
date_default_timezone_set('America/Chicago');

include 'bootstrap.php';

$jobby = new \Jobby\Jobby(array(
    'debug' => true
));

// Every job has a name
$jobby->add('CommandExample', array(
    'command' => 'php collect-fields.php',
    'schedule' => '* * * * *',
    'output' => 'log/command.log',
    'enabled' => false,
));

$jobby->add('ClosureExample', array(
    'command' => function () {
        echo "I'm a function!\n";
    },
    'schedule' => '* * * * *',
    'output' => 'log/closure.log',
    'enabled' => false,
));

$ran = $jobby->run();
