<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;

return function (App $app) {
    $container = $app->getContainer();

    // สมัครสมาชิก
    $app->post('/api/register', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        // ตรวจสอบข้อมูลที่จำเป็น
        if (!isset($data['name'], $data['email'], $data['password'], $data['phone_number'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // ตรวจสอบว่าอีเมลมีอยู่แล้วหรือไม่
        $user = DB::table('users')->where('email', $data['email'])->first();
        if ($user) {
            $response->getBody()->write(json_encode(['error' => 'อีเมลนี้ถูกใช้แล้ว']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // เข้ารหัสรหัสผ่าน
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // สร้างผู้ใช้ใหม่
        DB::table('users')->insert([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => $passwordHash,
            'role' => 'general',
        ]);

        $response->getBody()->write(json_encode(['message' => 'สมัครสมาชิกสำเร็จ']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // เข้าสู่ระบบ
    $app->post('/api/login', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        // ตรวจสอบข้อมูลที่จำเป็น
        if (!isset($data['email'], $data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // ค้นหาผู้ใช้
        $user = DB::table('users')->where('email', $data['email'])->first();

        if (!$user || !password_verify($data['password'], $user->password)) {
            $response->getBody()->write(json_encode(['error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // สร้าง JWT Token
        $now = new DateTime();
        $future = new DateTime("now +2 hours");
        $payload = [
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "sub" => $user->id,
            "role" => $user->role,
        ];

        $secret = "your_jwt_secret_key"; // ควรเก็บไว้ในไฟล์ .env หรือการตั้งค่าที่ปลอดภัย
        $token = JWT::encode($payload, $secret, "HS256");

        $response->getBody()->write(json_encode(['token' => $token, 'user' => $user]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
};
