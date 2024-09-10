<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        $authHeader = $request->getHeader('Authorization');
        error_log('Authorization Header: ' . print_r($authHeader, true));

        if (!$authHeader || !isset($authHeader[0])) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token not provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = explode(" ", $authHeader[0])[1];
        error_log('Token: ' . $token);

        if (!$token) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token format is invalid']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
                error_log('Decoded data: ' . print_r($decoded, true));
                error_log('Failed to decode token');
             // แสดงผล token ที่ decode แล้วใน log
            $request = $request->withAttribute('user', $decoded); // ใช้ request ที่ถูกปรับแต่ง
            error_log('Request Attributes after setting user: ' . print_r($request->getAttributes(), true));
            return $handler->handle($request); // ส่งต่อ request ที่ถูกปรับแต่ง
            
            
        } catch (Exception $e) {
            error_log('JWT decode failed: ' . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
    }
}
