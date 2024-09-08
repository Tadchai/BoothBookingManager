<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all booths
    $app->get('/api/booths', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT * FROM booths';
        $result = $conn->query($sql);
        $data = array();
        while ($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth);

    // Add a new booth (Admin Only)
    $app->post('/api/booths', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $conn = $GLOBALS['conn'];
        $stmt = $conn->prepare("INSERT INTO booths (booth_name, zone_id) VALUES (?, ?)");
        $stmt->bind_param("si", $data['booth_name'], $data['zone_id']);
        $stmt->execute();
        $boothId = $stmt->insert_id;

        $response->getBody()->write(json_encode(['message' => 'สร้างบูธสำเร็จ', 'booth_id' => $boothId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    })->add($auth)->add($admin);

    $app->put('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
        $boothId = $args['booth_id'];
        $data = $request->getParsedBody();
        $conn = $GLOBALS['conn'];
    
        $stmt = $conn->prepare("UPDATE booths SET booth_code = ?, booth_name = ?, booth_info = ?, zone_id = ? WHERE booth_id = ?");
        $stmt->bind_param("sssii", $data['booth_code'], $data['booth_name'], $data['booth_info'], $data['zone_id'], $boothId);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'อัปเดตบูธสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการอัปเดต']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
    
    $app->delete('/api/booths/{booth_id}', function (Request $request, Response $response, array $args) {
        $boothId = $args['booth_id'];
        $conn = $GLOBALS['conn'];
    
        $stmt = $conn->prepare("DELETE FROM booths WHERE booth_id = ?");
        $stmt->bind_param("i", $boothId);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'ลบบูธสำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบบูธที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
};
