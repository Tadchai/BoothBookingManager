<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;  // นำเข้า Key class

class AuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeader('Authorization');

        // ตรวจสอบว่ามี Authorization header และตรงตามรูปแบบ Bearer token
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader[0], $matches)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = $matches[1];
        $secret = "your_jwt_secret_key";

        try {
            // ใช้ Key class เพื่อระบุคีย์และอัลกอริทึมในการถอดรหัส
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // เพิ่มข้อมูลผู้ใช้ลงใน request attribute
            $request = $request->withAttribute('user', $decoded);
        } catch (Exception $e) {
            // ถ้าการถอดรหัส JWT ไม่สำเร็จ ให้ส่งข้อความแสดงข้อผิดพลาด
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Token ไม่ถูกต้อง']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // เรียก handler เพื่อดำเนินการต่อ
        return $handler->handle($request);
    }
}
