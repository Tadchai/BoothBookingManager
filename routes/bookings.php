<?php

use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all bookings by Id
    $app->get('/api/bookings/{user_id}', function (Request $request, Response $response, array $args) {
        $userId = $args['user_id'];
        $conn = $GLOBALS['conn'];

        // สร้างคำสั่ง SQL และใช้ prepared statement
        $sql = 'SELECT booths.booth_name, zones.zone_name, bookings.payment_price, bookings.status 
                FROM bookings 
                JOIN booths ON booths.booth_id = bookings.booth_id 
                JOIN zones ON zones.zone_id = booths.zone_id 
                WHERE bookings.user_id = ?';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // bind ค่า user_id
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = array();

        // ตรวจสอบผลลัพธ์และจัดเก็บข้อมูลลง array
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        // ปิด statement
        $stmt->close();

        // ส่งข้อมูลกลับในรูปแบบ JSON
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });


    // Add a new booking (User Only)
    $app->post('/api/bookings', function (Request $request, Response $response) {
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        //var_dump($data);
        // ทดสอบการใช้งานข้อมูล
        if (!isset($data['user_id']) || !isset($data['booth_id'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $conn = $GLOBALS['conn'];

        // ตรวจสอบว่าผู้ใช้จองบูธไปแล้วกี่บูธ
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ? AND status != 'canceled'");
        $stmt->bind_param("i", $data['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['total'] >= 4) {
            $response->getBody()->write(json_encode(['error' => 'จองบูธได้ไม่เกิน 4 บูธ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // เช็คว่าบูธว่างอยู่หรือไม่
        $stmt = $conn->prepare("SELECT booth_status FROM booths WHERE booth_id = ?");
        $stmt->bind_param("i", $data['booth_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['booth_status'] !== 'available') {
            $response->getBody()->write(json_encode(['error' => 'บูธนี้ถูกจองแล้ว']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // เพิ่มข้อมูลการจอง
        $stmt = $conn->prepare("INSERT INTO bookings (booking_date, products, booth_id, user_id, event_id) VALUES (CURRENT_TIMESTAMP(), ?, ?, ?, ?)");
        $stmt->bind_param("siii", $data['products'], $data['booth_id'], $data['user_id'], $data['event_id']);
        $stmt->execute();
        $bookingId = $stmt->insert_id;

        // เปลี่ยนสถานะ booth เป็น under_review
        $stmt = $conn->prepare("UPDATE booths SET booth_status = 'under_review' WHERE booth_id = ?");
        $stmt->bind_param("i", $data['booth_id']);
        $stmt->execute();

        // ตอบกลับเมื่อจองสำเร็จ
        $response->getBody()->write(json_encode(['message' => 'จองบูธสำเร็จ', 'booking_id' => $bookingId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // Approve Booking
    $app->put('/api/bookings/approve/{booking_id}', function (Request $request, Response $response, array $args) {
        $bookingId = $args['booking_id'];
        $conn = $GLOBALS['conn'];
    
        // ตรวจสอบสถานะการจอง
        $sql = 'SELECT status, booth_id FROM bookings WHERE booking_id = ?';
        $stmt = $conn->prepare($sql);
    
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        // Bind booking_id
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
    
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
    
        if (!$booking) {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบข้อมูลการจอง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    
        // เช็คสถานะการชำระเงิน
        if ($booking['status'] !== 'payment') {
            $response->getBody()->write(json_encode(['error' => 'สถานะการจองยังไม่ถูกชำระเงิน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // เปลี่ยนสถานะเป็น "approve"
        $sqlUpdate = 'UPDATE bookings SET status = "approve" WHERE booking_id = ?';
        $stmtUpdate = $conn->prepare($sqlUpdate);
    
        if ($stmtUpdate === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare update statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        $stmtUpdate->bind_param('i', $bookingId);
        $stmtUpdate->execute();
    
        // เปลี่ยนสถานะบูธเป็น "booked"
        $boothId = $booking['booth_id'];
        $sqlFinalize = 'UPDATE booths SET booth_status = "booked" WHERE booth_id = ?';
        $stmtFinalize = $conn->prepare($sqlFinalize);
    
        if ($stmtFinalize === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare finalize statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        $stmtFinalize->bind_param('i', $boothId);
        $stmtFinalize->execute();
    
        // ส่งการตอบกลับ
        $response->getBody()->write(json_encode(['message' => 'การจองอนุมัติและเปลี่ยนเป็นจองแล้วสำเร็จ']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
    
    // Canceled booking (User Only)
    $app->put('/api/bookings/{booking_id}', function (Request $request, Response $response, array $args) {
        $conn = $GLOBALS['conn'];

        // อัปเดตสถานะการจองเป็น 'Canceled'
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Canceled' WHERE booking_id = ?");
        $stmt->bind_param("i", $args['booking_id']);
        $stmt->execute();

        // ดึง booth_id จากการจอง
        $stmt = $conn->prepare("SELECT booth_id FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $args['booking_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();

        if ($booking) {
            // อัปเดตสถานะบูธเป็น 'available'
            $stmt = $conn->prepare("UPDATE booths SET booth_status = 'available' WHERE booth_id = ?");
            $stmt->bind_param("i", $booking['booth_id']);
            $stmt->execute();
        }

        // ตอบกลับเมื่อยกเลิกสำเร็จ
        $response->getBody()->write(json_encode(['message' => 'ยกเลิกการจองบูธสำเร็จ', 'booking_id' => $args['booking_id']]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

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
