<?php

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\AuthController;

$app->group('/api', function (RouteCollectorProxy $group) {
    $group->post('/register', [AuthController::class, 'register']);
    $group->post('/login', [AuthController::class, 'login']);
    $group->post('/logout', [AuthController::class, 'logout']);
});
