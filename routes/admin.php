<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/AdminMiddleware.php';

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all users (Admin Only)
    $app->get('/api/admin/users', function (Request $request, Response $response) {
        $users = DB::table('users')->get();
        $response->getBody()->write(json_encode($users));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth)->add($admin);

    // Delete a user (Admin Only)
    $app->delete('/api/admin/users/{user_id}', function (Request $request, Response $response, array $args) {
        $userId = $args['user_id'];

        $deleted = DB::table('users')->where('id', $userId)->delete();

        if ($deleted) {
            $response->getBody()->write(json_encode(['message' => 'ลบผู้ใช้สำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบผู้ใช้ที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
};
