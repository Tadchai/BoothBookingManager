<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token not provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = explode(" ", $authHeader[0])[1];

        try {
            $decoded = JWT::decode($token, new Key('your_jwt_secret_key', 'HS256'));
            $request = $request->withAttribute('user', $decoded);
            return $handler->handle($request);
        } catch (Exception $e) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
