<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Add a new events (Admin Only)
    $app->post('/api/events', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
    
        // ตรวจสอบข้อมูลก่อนทำการบันทึกลงฐานข้อมูล
        if (!isset($data['event_name'], $data['date'], $data['date_end'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // แปลงรูปแบบวันที่ให้เป็น YYYY-MM-DD
        $date = DateTime::createFromFormat('d/m/Y', $data['date']);
        $date_end = DateTime::createFromFormat('d/m/Y', $data['date_end']);
    
        if (!$date || !$date_end) {
            $response->getBody()->write(json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // แปลงวันที่ให้อยู่ในตัวแปร
        $dateFormatted = $date->format('Y-m-d');
        $dateEndFormatted = $date_end->format('Y-m-d');
    
        $stmt = $conn->prepare('INSERT INTO events (event_name, date, date_end) VALUES (?, ?, ?)');
        
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        $stmt->bind_param("sss", $data['event_name'], $dateFormatted, $dateEndFormatted);
        $stmt->execute();
        $eventId = $stmt->insert_id;
    
        $response->getBody()->write(json_encode(['message' => 'สร้าง event สำเร็จ', 'event_id' => $eventId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });
    
    //Update Event
    $app->put('/api/events/{event_id}', function (Request $request, Response $response, array $args) {
        $eventId = $args['event_id'];
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        $conn = $GLOBALS['conn'];
    
        // สร้างอาเรย์สำหรับเก็บการตั้งค่าที่จะอัปเดต
        $fields = [];
        $types = '';
        $values = [];
    
        // ตรวจสอบฟิลด์และเพิ่มข้อมูลที่ต้องการอัปเดตเข้าไปในอาเรย์
        if (isset($data['event_name'])) {
            $fields[] = "event_name = ?";
            $types .= 's';
            $values[] = $data['event_name'];
        }
    
        if (isset($data['date'])) {
            $fields[] = "date = ?";
            $types .= 's';
            $date = DateTime::createFromFormat('d/m/Y', $data['date']);
            if ($date) {
                $values[] = $date->format('Y-m-d');
            } else {
                $response->getBody()->write(json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }
    
        if (isset($data['date_end'])) {
            $fields[] = "date_end = ?";
            $types .= 's';
            $date_end = DateTime::createFromFormat('d/m/Y', $data['date_end']);
            if ($date_end) {
                $values[] = $date_end->format('Y-m-d');
            } else {
                $response->getBody()->write(json_encode(['error' => 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }
    
        // ตรวจสอบว่ามีฟิลด์ที่ต้องอัปเดตหรือไม่
        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'ไม่มีข้อมูลที่จะอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // สร้างคำสั่ง SQL โดยรวมฟิลด์ที่ต้องการอัปเดต
        $sql = "UPDATE events SET " . implode(", ", $fields) . " WHERE event_id = ?";
        $types .= 'i';
        $values[] = $eventId;
    
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        // ใช้ call_user_func_array สำหรับ bind_param เนื่องจากจำนวนและชนิดของฟิลด์ที่เปลี่ยนแปลง
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดต event สำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบ event ที่ต้องการอัปเดต หรือไม่มีการเปลี่ยนแปลง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    });
    
    

    // // Update events
    // $app->put('/api/events/{event_id}', function (Request $request, Response $response, array $args) {
    //     $eventId = $args['event_id'];
    //     $data = $request->getParsedBody();
    //     $conn = $GLOBALS['conn'];
    
    //     // ตรวจสอบข้อมูลก่อนทำการอัปเดตฐานข้อมูล
    //     if (!isset($data['event_name'], $data['event_date'], $data['event_date_end'])) {
    //         $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    //     }
    
    //     // แปลงรูปแบบวันที่ให้เป็น YYYY-MM-DD
    //     $eventDate = DateTime::createFromFormat('d/m/Y', $data['event_date']);
    //     $eventDateEnd = DateTime::createFromFormat('d/m/Y', $data['event_date_end']);
    
    //     if (!$eventDate || !$eventDateEnd) {
    //         $response->getBody()->write(json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    //     }
    
    //     // แปลงวันที่ให้อยู่ในตัวแปร
    //     $eventDateFormatted = $eventDate->format('Y-m-d');
    //     $eventDateEndFormatted = $eventDateEnd->format('Y-m-d');
    
    //     $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, event_date_end = ? WHERE event_id = ?");
    
    //     if ($stmt === false) {
    //         $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    //     }
    
    //     // ปรับ bind_param ให้ถูกต้อง
    //     $stmt->bind_param("sssi", $data['event_name'], $eventDateFormatted, $eventDateEndFormatted, $eventId);
    //     $stmt->execute();
    
    //     // ตรวจสอบผลการอัปเดต
    //     if ($stmt->affected_rows > 0) {
    //         $response->getBody()->write(json_encode(['message' => 'อัปเดต event สำเร็จ']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    //     } else {
    //         $response->getBody()->write(json_encode(['error' => 'ไม่พบ event ที่ต้องการอัปเดต']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    //     }
    // });    
};
