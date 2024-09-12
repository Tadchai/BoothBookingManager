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

    // Get all zones
    $app->get('/api/zones', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT zone_id, zone_name, zone_info, number_of_booths FROM zones';
        $result = $conn->query($sql);
        
        if ($result === false) {
            $response->getBody()->write(json_encode(['error' => 'Database query failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    // Add a new zone (Admin Only)
    $app->post('/api/zones', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);

        // ตรวจสอบข้อมูลก่อนทำการบันทึกลงฐานข้อมูล
        if (!isset($data['zone_name'], $data['zone_info'], $data['number_of_booths'], $data['event_id'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $stmt = $conn->prepare('INSERT INTO zones (zone_name, zone_info, number_of_booths, event_id) VALUES (?, ?, ?, ?)');
        
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $stmt->bind_param("ssii", $data['zone_name'], $data['zone_info'], $data['number_of_booths'], $data['event_id']);
        $stmt->execute();
        $zoneId = $stmt->insert_id;

        $response->getBody()->write(json_encode(['message' => 'สร้างโซนสำเร็จ', 'zone_id' => $zoneId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // Update zone
    $app->put('/api/zones/{zone_id}', function (Request $request, Response $response, array $args) {
        $zoneId = $args['zone_id'];
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        $conn = $GLOBALS['conn'];
    
        // ตรวจสอบการถอดรหัส JSON
        if (is_null($data)) {
            $response->getBody()->write(json_encode(['error' => 'รูปแบบข้อมูลไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // สร้างอาเรย์สำหรับเก็บการตั้งค่าที่จะอัปเดต
        $fields = [];
        $types = '';
        $values = [];
    
        // ตรวจสอบฟิลด์และเพิ่มข้อมูลที่ต้องการอัปเดตเข้าไปในอาเรย์
        if (isset($data['zone_name'])) {
            $fields[] = "zone_name = ?";
            $types .= 's';
            $values[] = $data['zone_name'];
        }
    
        if (isset($data['zone_info'])) {
            $fields[] = "zone_info = ?";
            $types .= 's';
            $values[] = $data['zone_info'];
        }
    
        if (isset($data['number_of_booths'])) {
            // ตรวจสอบว่า number_of_booths เป็นจำนวนเต็ม
            if (!is_int($data['number_of_booths'])) {
                $response->getBody()->write(json_encode(['error' => 'number_of_booths ต้องเป็นจำนวนเต็ม']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = "number_of_booths = ?";
            $types .= 'i';
            $values[] = $data['number_of_booths'];
        }
    
        // ตรวจสอบว่ามีฟิลด์ที่ต้องอัปเดตหรือไม่
        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'ไม่มีข้อมูลที่จะอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // สร้างคำสั่ง SQL โดยรวมฟิลด์ที่ต้องการอัปเดต
        $sql = "UPDATE zones SET " . implode(", ", $fields) . " WHERE zone_id = ?";
        $types .= 'i';
        $values[] = $zoneId;
    
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
        // ใช้ call_user_func_array สำหรับ bind_param เนื่องจากจำนวนและชนิดของฟิลด์ที่เปลี่ยนแปลง
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดต zone สำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบ zone ที่ต้องการอัปเดต หรือไม่มีการเปลี่ยนแปลง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    });
    
    // $app->put('/api/zones/{zone_id}', function (Request $request, Response $response, array $args) {
    //     $zoneId = $args['zone_id'];
    //     $data = $request->getParsedBody();
    //     $conn = $GLOBALS['conn'];
    
    //     if (!isset($data['zone_code'], $data['zone_name'], $data['zone_info'], $data['number_of_booths'])) {
    //         $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    //     }

    //     $stmt = $conn->prepare("UPDATE zones SET zone_code = ?, zone_name = ?, zone_info = ?, number_of_booths = ? WHERE zone_id = ?");
        
    //     if ($stmt === false) {
    //         $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    //     }

    //     $stmt->bind_param("sssii", $data['zone_code'], $data['zone_name'], $data['zone_info'], $data['number_of_booths'], $zoneId);
    //     $stmt->execute();
    
    //     if ($stmt->affected_rows > 0) {
    //         $response->getBody()->write(json_encode(['message' => 'อัปเดตโซนสำเร็จ']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    //     } else {
    //         $response->getBody()->write(json_encode(['error' => 'ไม่พบโซนที่ต้องการอัปเดต']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    //     }
    // })->add($auth)->add($admin);
    
    // Delete zone
    $app->delete('/api/zones/{zone_id}', function (Request $request, Response $response, array $args) {
        $zoneId = $args['zone_id'];
        $conn = $GLOBALS['conn'];
    
        $stmt = $conn->prepare("DELETE FROM zones WHERE zone_id = ?");
        
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $stmt->bind_param("i", $zoneId);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'ลบโซนสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบโซนที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    });
};
