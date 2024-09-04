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
(require __DIR__ . '/../routes/auth.php')($app);
(require __DIR__ . '/../routes/zones.php')($app);
(require __DIR__ . '/../routes/booths.php')($app);
(require __DIR__ . '/../routes/bookings.php')($app);
(require __DIR__ . '/../routes/payments.php')($app);
(require __DIR__ . '/../routes/admin.php')($app);

$app->addErrorMiddleware(true, true, true);

$app->run();
