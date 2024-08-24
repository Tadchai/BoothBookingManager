<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class AuthController
{
    public function register(Request $request, Response $response)
{
    $data = $request->getParsedBody();
    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    $user = User::create($data);

    $response->getBody()->write(json_encode([
        'message' => 'User registered successfully',
        'user' => $user
    ]));

    return $response->withHeader('Content-Type', 'application/json');
}

public function login(Request $request, Response $response)
{
    $data = $request->getParsedBody();
    $user = User::where('email', $data['email'])->first();

    if (!$user || !password_verify($data['password'], $user->password)) {
        $response->getBody()->write(json_encode(['message' => 'Invalid email or password']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Login successful',
        'user' => $user
    ]));

    return $response->withHeader('Content-Type', 'application/json');
}

public function logout(Request $request, Response $response)
{
    // ลบ session หรือ JWT token ที่นี่

    $response->getBody()->write(json_encode(['message' => 'Logout successful']));
    return $response->withHeader('Content-Type', 'application/json');
}

}
