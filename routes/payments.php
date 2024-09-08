<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all payments
    $app->get('/api/payments', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT * FROM payments';
        $result = $conn->query($sql);
        $data = array();
        while ($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new payment (User Only)
    $app->post('/api/payments', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("INSERT INTO payments (user_id, booking_id, payment_amount, payment_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $data['user_id'], $data['booking_id'], $data['payment_amount'], $data['payment_date']);
        $stmt->execute();
        $paymentId = $stmt->insert_id;

        $response->getBody()->write(json_encode(['message' => 'ชำระเงินสำเร็จ', 'payment_id' => $paymentId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth);
};
