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

    
    // Add a new payment (Member)
    $app->post('/api/payments', function (Request $request, Response $response) {
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        $conn = $GLOBALS['conn'];

        // ดึงข้อมูลวันจัดงานจากการจอง
        $stmt = $conn->prepare("SELECT date FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $data['event_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if (!$event) {
            $response->getBody()->write(json_encode(['message' => 'ไม่พบข้อมูลงาน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // ตรวจสอบจำนวนวันก่อนวันจัดงาน
        $today = new DateTime();
        $eventDate = new DateTime($event['date']);
        $interval = $today->diff($eventDate)->days;

        if ($interval < 5) {
            // ถ้าวันก่อนงานน้อยกว่า 5 วัน
            $stmt = $conn->prepare("UPDATE bookings SET status = 'canceled' WHERE booking_id = ?");
            $stmt->bind_param("i", $data['booking_id']);
            $stmt->execute();

            // ดึง booth_id จากการจอง
            $stmt = $conn->prepare("SELECT booth_id FROM bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $data['booking_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();

            if ($booking) {
                $stmt = $conn->prepare("UPDATE booths SET booth_status = 'available' WHERE booth_id = ?");
                $stmt->bind_param("i", $booking['booth_id']);
                $stmt->execute();
            }

            $response->getBody()->write(json_encode(['message' => 'ชำระเงินไม่ได้', 'status' => 'ยกเลิกการจอง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } else {
            // ถ้าวันก่อนงานมากกว่าหรือเท่ากับ 5 วัน ชำระเงินตามปกติ    
            // อัปเดตสถานะการจองเป็น 'ชำระเงิน'
            $stmt = $conn->prepare("UPDATE bookings SET payment_date = CURRENT_TIMESTAMP(), status = 'payment', payment_slip = ?, payment_price = ? WHERE booking_id = ?");
            $stmt->bind_param("sii", $data['payment_slip'], $data['payment_price'], $data['booking_id']);
            $stmt->execute();

            // ดึง booth_id จากการจอง
            $stmt = $conn->prepare("SELECT booth_id FROM bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $data['booking_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();

            if ($booking) {
                // อัปเดตสถานะบูธเป็น 'booked'
                $stmt = $conn->prepare("UPDATE booths SET booth_status = 'booked' WHERE booth_id = ?");
                $stmt->bind_param("i", $booking['booth_id']);
                $stmt->execute();
            }

            $response->getBody()->write(json_encode(['message' => 'ชำระเงินสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }
    })->add($auth);
};
