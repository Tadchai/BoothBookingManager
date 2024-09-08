<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AdminMiddleware {
    public function __invoke(Request $request, Handler $handler): Response {
        $user = $request->getAttribute('user');

        if (!$user || $user->role !== 'admin') {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Admin access required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        return $handler->handle($request);
    }
}
