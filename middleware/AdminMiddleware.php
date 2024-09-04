<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;

class AdminMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $user = $request->getAttribute('user');

        if ($user->role !== 'admin') {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'ต้องเป็นผู้ดูแลระบบเท่านั้น']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        return $handler->handle($request);
    }
}
