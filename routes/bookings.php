<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all bookings
    $app->get('/api/bookings', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT * FROM bookings';
        $result = $conn->query($sql);
        $data = array();
        while ($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new booking (User Only)
    $app->post('/api/bookings', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, booth_id, booking_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $data['user_id'], $data['booth_id'], $data['booking_date']);
        $stmt->execute();
        $bookingId = $stmt->insert_id;

        $response->getBody()->write(json_encode(['message' => 'จองบูธสำเร็จ', 'booking_id' => $bookingId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth);

    $app->delete('/api/bookings/{booking_id}', function (Request $request, Response $response, array $args) {
        $user = $request->getAttribute('user');
        $bookingId = $args['booking_id'];
        $conn = $GLOBALS['conn'];
        
        $stmt = $conn->prepare("DELETE FROM bookings WHERE user_id = ? AND booking_id = ?");
        $stmt->bind_param("ii", $user->id, $bookingId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'ยกเลิกการจองสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบการจองที่ต้องการยกเลิก']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth);
};
