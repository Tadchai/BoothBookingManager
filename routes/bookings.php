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
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
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
