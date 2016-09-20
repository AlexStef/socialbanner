<?php

if (!getenv('APP_ENV')) {
    putenv('APP_ENV=prod');
}

require_once __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../app/app.php';
$app->run();
