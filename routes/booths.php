<?php

use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all booths
    $app->get('/api/booths', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT booth_id, booth_name, booth_size, booth_status, price FROM booths';
        $result = $conn->query($sql);
        $data = array();
        while ($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });


    // Add a new booth (Admin)
    $app->post('/api/booths', function (Request $request, Response $response) {
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("INSERT INTO booths (booth_name, booth_size, booth_products, price, zone_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $data['booth_name'], $data['booth_size'], $data['booth_products'], $data['price'], $data['zone_id']);
        $stmt->execute();
        $boothId = $stmt->insert_id;

        $response->getBody()->write(json_encode(['message' => 'สร้างบูธสำเร็จ', 'booth_id' => $boothId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth)->add($admin);


    // Update booth (Admin)
    $app->put('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
        $boothId = $args['booth_id'];
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
        if (isset($data['booth_name'])) {
            $fields[] = "booth_name = ?";
            $types .= 's';
            $values[] = $data['booth_name'];
        }

        if (isset($data['booth_size'])) {
            $fields[] = "booth_size = ?";
            $types .= 's';
            $values[] = $data['booth_size'];
        }

        if (isset($data['booth_products'])) {
            $fields[] = "booth_products = ?";
            $types .= 's';
            $values[] = $data['booth_products'];
        }

        if (isset($data['price'])) {
            $fields[] = "price = ?";
            $types .= 's';
            $values[] = $data['price'];
        }

        if (isset($data['zone_id'])) {
            $fields[] = "zone_id = ?";
            $types .= 'i';
            $values[] = $data['zone_id'];
        }

        // ตรวจสอบว่ามีฟิลด์ที่ต้องอัปเดตหรือไม่
        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'ไม่มีข้อมูลที่จะอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // สร้างคำสั่ง SQL โดยรวมฟิลด์ที่ต้องการอัปเดต
        $sql = "UPDATE booths SET " . implode(", ", $fields) . " WHERE booth_id = ?";
        $types .= 'i';
        $values[] = $boothId;

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // ใช้ call_user_func_array สำหรับ bind_param เนื่องจากจำนวนและชนิดของฟิลด์ที่เปลี่ยนแปลง
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดต booth สำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบ booth ที่ต้องการอัปเดต หรือไม่มีการเปลี่ยนแปลง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);


    //Delete Booth (Admin)
    $app->delete('/api/booths/{booth_name}', function (Request $request, Response $response, array $args) {
        $boothname = $args['booth_name'];
        $conn = $GLOBALS['conn'];

        $stmt = $conn->prepare("DELETE FROM booths WHERE booth_name = ?");
        $stmt->bind_param("s", $boothname);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'ลบบูธสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);


    // Get all booths (Admin)
    $app->get('/api/booths/admin', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT first_name, last_name, zone_name, price, booth_name, booth_status
            FROM bookings 
            JOIN users ON users.user_id = bookings.user_id
            JOIN booths ON booths.booth_id = bookings.booth_id
            JOIN zones ON zones.zone_id = booths.zone_id ;';

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
    })->add($auth)->add($admin);

    // Get all booths by zone_Id (Member)
    $app->get('/api/booths/{zone_id}', function (Request $request, Response $response, array $args) {
        $zoneId = $args['zone_id'];
        $conn = $GLOBALS['conn'];

        // สร้างคำสั่ง SQL และใช้ prepared statement
        $sql = 'SELECT booth_id, booth_name, booth_size, booth_products, booth_status, price
                    FROM booths 
                WHERE zone_id = ?';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // bind ค่า zone_id
        $stmt->bind_param('i', $zoneId);
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
    })->add($auth);
};

    // $app->put('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
    //     $boothId = $args['booth_id'];
    //     $data = $request->getParsedBody();
    //     $conn = $GLOBALS['conn'];

    //     $stmt = $conn->prepare("UPDATE booths SET booth_code = ?, booth_name = ?, booth_info = ?, zone_id = ? WHERE booth_id = ?");
    //     $stmt->bind_param("sssii", $data['booth_code'], $data['booth_name'], $data['booth_info'], $data['zone_id'], $boothId);
    //     $stmt->execute();

    //     if ($stmt->affected_rows > 0) {
    //         $response->getBody()->write(json_encode(['message' => 'อัปเดตบูธสำเร็จ']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    //     } else {
    //         $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการอัปเดต']));
    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    //     }
    // })->add($auth)->add($admin);