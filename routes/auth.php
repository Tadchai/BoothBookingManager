<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateToken($user) {
    $key = 'your_secret_key'; // ใส่ secret key ของคุณ
    $payload = [
        'iss' => 'your_domain.com',  // issuer
        'aud' => 'your_domain.com',  // audience
        'iat' => time(),             // เวลาออก token
        'exp' => time() + (60 * 60), // หมดอายุใน 1 ชั่วโมง
        'userId' => $user['id'],     // user ID
        'role' => $user['role'],     // บทบาทผู้ใช้
    ];

    return JWT::encode($payload, $key, 'HS256');
}

return function (App $app) {
    // API สำหรับการ Login
    $app->post('/api/login', function (Request $request, Response $response) {
        // ดึงข้อมูลจาก body ของ request
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);

        // ตรวจสอบว่าข้อมูลที่ส่งมาไม่ว่างและมี email/password ครบถ้วน
        if (is_null($data) || !isset($data['email']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // ตรวจสอบ email และ password ในฐานข้อมูล
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // ถ้าพบผู้ใช้และ password ถูกต้อง
        if ($user && password_verify($data['password'], $user['password'])) {
            $token = generateToken($user);  // สร้าง JWT token
            $response->getBody()->write(json_encode(['token' => $token]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            // ถ้า email หรือ password ไม่ถูกต้อง
            $response->getBody()->write(json_encode(['error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    });

    $app->post('/api/register', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
    
        if (strpos($request->getHeaderLine('Content-Type'), 'application/json') === false) {
            $response->getBody()->write(json_encode(['error' => 'Content-Type ไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        $rawData = $request->getBody()->getContents();
        $data = json_decode($rawData, true);
    
        if (is_null($data)) {
            $response->getBody()->write(json_encode(['error' => 'ไม่ได้รับข้อมูล']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // Check required fields
        if (!isset( $data['first_name'], $data['last_name'], $data['title'], $data['email'], $data['password'], $data['phone_number'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $response->getBody()->write(json_encode(['error' => 'อีเมลนี้ถูกใช้แล้ว']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        // Hash the password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
        // Insert into the database
        $stmt = $conn->prepare("INSERT INTO users (title, first_name, last_name, email, phone_number, password, role) VALUES (?, ?, ?, ?, ?, ?, 'member')");
        $stmt->bind_param("ssssss", $data['title'], $data['first_name'], $data['last_name'], $data['email'], $data['phone_number'], $passwordHash);
        $stmt->execute();
    
        $response->getBody()->write(json_encode(['message' => 'สมัครสมาชิกสำเร็จ']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });
    
};
