<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../routes/auth.php';
require __DIR__ . '/../routes/zones.php';
require __DIR__ . '/../routes/booths.php';
require __DIR__ . '/../routes/bookings.php';
require __DIR__ . '/../routes/payments.php';
require __DIR__ . '/../routes/admin.php';

$app->run();
