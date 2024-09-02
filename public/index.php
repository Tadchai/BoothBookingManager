<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();

require __DIR__ . '/../config/db.php';

// Middleware สำหรับจัดการ CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// รวมไฟล์ routes
require __DIR__ . '/../routes/auth.php';
require __DIR__ . '/../routes/zones.php';
require __DIR__ . '/../routes/booths.php';
require __DIR__ . '/../routes/bookings.php';
require __DIR__ . '/../routes/payments.php';
require __DIR__ . '/../routes/admin.php';
$app->addErrorMiddleware(true, true, true);

$app->run();
