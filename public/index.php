<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;

$app = AppFactory::create();

require __DIR__ . '/../config/db.php';

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Load routes
(require __DIR__ . '/../routes/auth.php')($app);
(require __DIR__ . '/../routes/zones.php')($app);
(require __DIR__ . '/../routes/booths.php')($app);
(require __DIR__ . '/../routes/bookings.php')($app);
(require __DIR__ . '/../routes/payments.php')($app);
(require __DIR__ . '/../routes/admin.php')($app);
(require __DIR__ . '/../routes/events.php')($app);
(require __DIR__ . '/../routes/users.php')($app);

$app->run();
