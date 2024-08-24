<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthMiddleware
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // ตรวจสอบ JWT token หรือ session ที่นี่
        return $next($request, $response);
    }
}
