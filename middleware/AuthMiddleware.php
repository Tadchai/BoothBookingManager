<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        // ดึง Authorization Header
        $authHeader = $request->getHeader('Authorization');
        error_log('Authorization Header in AuthMiddleware: ' . print_r($authHeader, true));

        // ตรวจสอบว่า Authorization Header มีอยู่และมีค่า
        if (!$authHeader || !isset($authHeader[0])) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token not provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // แยก Token จาก Header
        $token = explode(" ", $authHeader[0])[1];
        error_log('Token in AuthMiddleware: ' . $token);

        if (!$token) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token format is invalid']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // พยายาม Decode JWT เพื่อยืนยันความถูกต้องของ token
        try {
            $decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
            error_log('Decoded data in AuthMiddleware: ' . print_r($decoded, true));
            
            // ถ้า decode สำเร็จ ส่งต่อ request ไปยัง handler
            return $handler->handle($request);

        } catch (Exception $e) {
            error_log('JWT decode failed in AuthMiddleware: ' . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
