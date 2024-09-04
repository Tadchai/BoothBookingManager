<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

return function (App $app) {
    $auth = new AuthMiddleware();

    // Get all payments for a user
    $app->get('/api/payments', function (Request $request, Response $response) {
        $user = $request->getAttribute('user');

        $payments = DB::table('payments')
            ->where('user_id', $user->id)
            ->get();

        $response->getBody()->write(json_encode($payments));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new payment
    $app->post('/api/payments', function (Request $request, Response $response) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $paymentId = DB::table('payments')->insertGetId([
            'user_id' => $user->id,
            'booking_id' => $data['booking_id'],
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'],
            'status' => 'completed',
        ]);

        $response->getBody()->write(json_encode(['message' => 'ชำระเงินสำเร็จ', 'payment_id' => $paymentId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth);
};
