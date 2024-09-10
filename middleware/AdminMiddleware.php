<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AdminMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        // ดึงข้อมูลผู้ใช้จาก request ที่ AuthMiddleware ได้บรรจุไว้
        $user = $request->getAttribute('user');
        error_log('Attributes in AdminMiddleware: ' . print_r($request->getAttributes(), true));
        error_log('user data: ' . print_r($user, true));

        // ตรวจสอบว่าข้อมูลผู้ใช้ถูกต้องและ role เป็น 'admin'
        if (!$user || !isset($user->role) || $user->role !== 'admin') {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Admin access required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // ส่งต่อ request ไปยัง handler ถ้าผ่านการตรวจสอบ
        return $handler->handle($request);
    }
}
