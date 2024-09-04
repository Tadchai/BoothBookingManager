<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

return function (App $app) {
    $auth = new AuthMiddleware();

    // Get all bookings for a user
    $app->get('/api/bookings', function (Request $request, Response $response) {
        $user = $request->getAttribute('user');

        $bookings = DB::table('bookings')
            ->where('user_id', $user->id)
            ->get();

        $response->getBody()->write(json_encode($bookings));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new booking
    $app->post('/api/bookings', function (Request $request, Response $response) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();

        $bookingId = DB::table('bookings')->insertGetId([
            'user_id' => $user->id,
            'booth_id' => $data['booth_id'],
            'booking_date' => $data['booking_date'],
            'status' => 'pending',
        ]);

        $response->getBody()->write(json_encode(['message' => 'จองสำเร็จ', 'booking_id' => $bookingId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth);

    // Cancel a booking
    $app->delete('/api/bookings/{booking_id}', function (Request $request, Response $response, array $args) {
        $user = $request->getAttribute('user');
        $bookingId = $args['booking_id'];

        $deleted = DB::table('bookings')
            ->where('user_id', $user->id)
            ->where('booking_id', $bookingId)
            ->delete();

        if ($deleted) {
            $response->getBody()->write(json_encode(['message' => 'ยกเลิกการจองสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบการจองที่ต้องการยกเลิก']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth);
};