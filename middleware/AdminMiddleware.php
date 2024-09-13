<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AdminMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader || !isset($authHeader[0])) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token not provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = explode(" ", $authHeader[0])[1];

        if (!$token) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Authorization token format is invalid']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
            if (!isset($decoded->role) || $decoded->role !== 'admin') {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'Admin access required']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
            return $handler->handle($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            error_log('JWT expired: ' . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Expired token', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        } catch (Exception $e) {
            error_log('JWT decode failed: ' . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token', 'message' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
