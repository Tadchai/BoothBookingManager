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

    // Get all booths
    $app->get('/api/booths', function (Request $request, Response $response) {
        $booths = DB::table('booths')->get();

        $response->getBody()->write(json_encode($booths));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new booth (Admin Only)
    $app->post('/api/booths', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $boothId = DB::table('booths')->insertGetId([
            'booth_code' => $data['booth_code'],
            'booth_name' => $data['booth_name'],
            'booth_info' => $data['booth_info'],
            'zone_id' => $data['zone_id'],
        ]);

        $response->getBody()->write(json_encode(['message' => 'สร้างบูธสำเร็จ', 'booth_id' => $boothId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth)->add($admin);

    // Update a booth (Admin Only)
    $app->put('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
        $boothId = $args['booth_id'];
        $data = $request->getParsedBody();

        $updated = DB::table('booths')->where('booth_id', $boothId)->update([
            'booth_code' => $data['booth_code'],
            'booth_name' => $data['booth_name'],
            'booth_info' => $data['booth_info'],
            'zone_id' => $data['zone_id'],
        ]);

        if ($updated) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดตบูธสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);

    // Delete a booth (Admin Only)
    $app->delete('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
        $boothId = $args['booth_id'];

        $deleted = DB::table('booths')->where('booth_id', $boothId)->delete();

        if ($deleted) {
            $response->getBody()->write(json_encode(['message' => 'ลบบูธสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
};
