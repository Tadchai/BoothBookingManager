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

    // Update User
    $app->put('/api/users/{first_name}/{last_name}', function (Request $request, Response $response, array $args) {
        $firstName = $args['first_name'];
        $lastName = $args['last_name'];
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
        $conn = $GLOBALS['conn'];

        // สร้างอาเรย์สำหรับเก็บการตั้งค่าที่จะอัปเดต
        $fields = [];
        $types = '';
        $values = [];

        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $types .= 's';
            $values[] = $data['title'];
        }

        // ตรวจสอบและเพิ่มข้อมูลที่จะอัปเดต (เฉพาะชื่อ)
        if (isset($data['first_name'])) {
            $fields[] = "first_name = ?";
            $types .= 's';
            $values[] = $data['first_name'];
        }

        // ตรวจสอบและเพิ่มข้อมูลที่จะอัปเดต (เฉพาะนามสกุล)
        if (isset($data['last_name'])) {
            $fields[] = "last_name = ?";
            $types .= 's';
            $values[] = $data['last_name'];
        }

        // ตรวจสอบและเพิ่มข้อมูลที่จะอัปเดต (เฉพาะเบอร์โทรศัพท์)
        if (isset($data['phone_number'])) {
            if (!preg_match('/^[0-9]{10}$/', $data['phone_number'])) {
                $response->getBody()->write(json_encode(['error' => 'เบอร์โทรศัพท์ไม่ถูกต้อง']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = "phone_number = ?";
            $types .= 's';
            $values[] = $data['phone_number'];
        }

        // ตรวจสอบและเพิ่มข้อมูลที่จะอัปเดต (เฉพาะอีเมล)
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $response->getBody()->write(json_encode(['error' => 'อีเมลไม่ถูกต้อง']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // ตรวจสอบว่าอีเมลซ้ำหรือไม่
            $checkEmailSql = "SELECT * FROM users WHERE email = ? AND first_name != ? AND last_name != ?";
            $checkStmt = $conn->prepare($checkEmailSql);

            if ($checkStmt === false) {
                $response->getBody()->write(json_encode(['error' => 'Failed to prepare email check statement']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $checkStmt->bind_param('sss', $data['email'], $firstName, $lastName);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $response->getBody()->write(json_encode(['error' => 'อีเมลนี้ถูกใช้แล้ว']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $fields[] = "email = ?";
            $types .= 's';
            $values[] = $data['email'];
        }

        // ตรวจสอบว่ามีข้อมูลที่ต้องอัปเดตหรือไม่
        if (empty($fields)) {
            $response->getBody()->write(json_encode(['error' => 'ไม่มีข้อมูลที่จะอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // สร้างคำสั่ง SQL โดยรวมฟิลด์ที่ต้องการอัปเดต
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE first_name = ? AND last_name = ?";
        $types .= 'ss';
        $values[] = $firstName;
        $values[] = $lastName;

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $response->getBody()->write(json_encode(['error' => 'Failed to prepare statement']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // ใช้ call_user_func_array สำหรับ bind_param เนื่องจากจำนวนและชนิดของฟิลด์ที่เปลี่ยนแปลง
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดตข้อมูลสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบผู้ใช้ที่ต้องการอัปเดต หรือไม่มีการเปลี่ยนแปลง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    });

    // Get All User
    $app->get('/api/users', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT first_name, last_name, phone_number, email FROM users WHERE role ="member"';
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

    // Get All User NO Payment
    $app->get('/api/users/unpay', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT first_name, last_name, phone_number, booth_name, zone_name 
                FROM bookings 
                JOIN users ON users.user_id = bookings.user_id
                JOIN booths ON booths.booth_id = bookings.booth_id
                JOIN zones ON zones.zone_id = booths.zone_id
                WHERE  status = "reserve";';

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

    // Get All User Payment
    $app->get('/api/users/pay', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT first_name, last_name, phone_number, booth_name, zone_name 
                    FROM bookings 
                    JOIN users ON users.user_id = bookings.user_id
                    JOIN booths ON booths.booth_id = bookings.booth_id
                    JOIN zones ON zones.zone_id = booths.zone_id
                    WHERE status = "payment";';

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

    // Get All User under_review
    $app->get('/api/users/under_review', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT first_name, last_name, phone_number, booth_name, zone_name 
        FROM bookings 
        JOIN users ON users.user_id = bookings.user_id
        JOIN booths ON booths.booth_id = bookings.booth_id
        JOIN zones ON zones.zone_id = booths.zone_id
        WHERE booth_status = "under_review" AND status != "canceled";';

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
};
