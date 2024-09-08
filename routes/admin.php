<?php
use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    $auth = new AuthMiddleware();
    $admin = new AdminMiddleware();

    // Get all users (Admin Only)
    $app->get('/api/admin/users', function (Request $request, Response $response) {
        $conn = $GLOBALS['conn'];
        $sql = 'SELECT * FROM users';
        $result = $conn->query($sql);
        $data = array();
        while ($row = $result->fetch_assoc()) {
            array_push($data, $row);
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    })->add($auth)->add($admin);

    $app->delete('/api/admin/users/{user_id}', function (Request $request, Response $response, array $args) {
        $userId = $args['user_id'];
        $conn = $GLOBALS['conn'];
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $response->getBody()->write(json_encode(['message' => 'ลบผู้ใช้สำเร็จ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } else {
            $response->getBody()->write(json_encode(['error' => 'ไม่พบผู้ใช้ที่ต้องการลบ']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
    })->add($auth)->add($admin);
};
