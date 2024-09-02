<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    public function __invoke(Request $request, Response $response, $next) {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = explode(" ", $authHeader[0])[1];

        try {
            $secret = "your_jwt_secret_key";
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $request = $request->withAttribute('user', $decoded);
            $response = $next($request, $response);
            return $response;
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}

class AdminMiddleware {
    public function __invoke(Request $request, Response $response, $next) {
        $user = $request->getAttribute('user');

        if ($user->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Forbidden']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $response = $next($request, $response);
        return $response;
    }
}
