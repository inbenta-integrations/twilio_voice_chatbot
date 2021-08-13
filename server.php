<?php

require "vendor/autoload.php";

use Inbenta\TwilioConnector\TwilioConnector;

//Instance new TwilioConnector
$appPath=__DIR__.'/';
$app = new TwilioConnector($appPath);

//Handle the incoming request
$response = $app->handleRequest();

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_SLASHES);
