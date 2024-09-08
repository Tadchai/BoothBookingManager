<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateToken($user) {
    $key = 'your_secret_key'; // เปลี่ยนเป็นคีย์ลับของคุณ
    $payload = [
        'iss' => 'your_domain.com', // issuer
        'aud' => 'your_domain.com', // audience
        'iat' => time(), // issued at
        'exp' => time() + (60 * 60), // expired (1 ชั่วโมง)
        'userId' => $user['id'], // ข้อมูลผู้ใช้
        'role' => $user['role'], // บทบาทผู้ใช้
    ];

    return JWT::encode($payload, $key, 'HS256');
}

function decodeToken($jwt) {
    $key = 'your_secret_key'; // เปลี่ยนเป็นคีย์ลับของคุณ
    try {
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        return null; // หากการถอดรหัสล้มเหลว
    }
}

return function (App $app) {
    // Login API
    $app->post('/api/login', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $data['email'], $data['password']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    
        if ($user) {
            $token = generateToken($user); // เรียกใช้ฟังก์ชัน generateToken เพื่อสร้าง JWT
            $response->getBody()->write(json_encode(['token' => $token]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
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
    
        if (!isset($data['name'], $data['email'], $data['password'], $data['phone_number'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $response->getBody()->write(json_encode(['error' => 'อีเมลนี้ถูกใช้แล้ว']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone_number, password, role) VALUES (?, ?, ?, ?, 'general')");
        $stmt->bind_param("ssss", $data['name'], $data['email'], $data['phone_number'], $passwordHash);
        $stmt->execute();
    
        $response->getBody()->write(json_encode(['message' => 'สมัครสมาชิกสำเร็จ']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});
};
