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

    // Get all zones
    $app->get('/api/zones', function (Request $request, Response $response) {
        $zones = DB::table('zones')->get();
        $response->getBody()->write(json_encode($zones));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new zone (Admin Only)
    $app->post('/api/zones', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $zoneId = DB::table('zones')->insertGetId([
            'zone_code' => $data['zone_code'],
            'zone_name' => $data['zone_name'],
            'zone_info' => $data['zone_info'],
            'number_of_booths' => $data['number_of_booths'],
        ]);

        $response->getBody()->write(json_encode(['message' => 'สร้างโซนสำเร็จ', 'zone_id' => $zoneId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth)->add($admin);

    // Update a zone (Admin Only)
    $app->put('/api/zones/{zone_id}', function (Request $request, Response $response, array $args) {
        $zoneId = $args['zone_id'];
        $data = $request->getParsedBody();

        $updated = DB::table('zones')->where('zone_id', $zoneId)->update([
            'zone_code' => $data['zone_code'],
            'zone_name' => $data['zone_name'],
            'zone_info' => $data['zone_info'],
            'number_of_booths' => $data['number_of_booths'],
        ]);

        if ($updated) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดตโซนสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบโซนที่ต้องการอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);

    // Delete a zone (Admin Only)
    $app->delete('/api/zones/{zone_id}', function (Request $request, Response $response, array $args) {
        $zoneId = $args['zone_id'];

        $deleted = DB::table('zones')->where('zone_id', $zoneId)->delete();

        if ($deleted) {
            $response->getBody()->write(json_encode(['message' => 'ลบโซนสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบโซนที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
};
