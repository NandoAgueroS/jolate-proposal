<?php
/**
 * JOLATE 2026 — Admin: inscripto detail (JSON)
 */

date_default_timezone_set('UTC');
require __DIR__ . '/../auth.php';
admin_require();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'server_config']);
    exit;
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_id']);
    exit;
}

try {
    $pdo = get_pdo($config);
    $sql = "SELECT i.id, i.nombre, i.institucion, i.email, i.dni,"
         . " i.titulo_ponencia, i.eje_tematico, i.archivo_filename,"
         . " i.created_at, t.nombre AS rol"
         . " FROM `jolate_inscriptos` i"
         . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
         . " WHERE i.id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    $row['id'] = (int)$row['id'];
    $row['tiene_pdf'] = !empty($row['archivo_filename']);
    echo json_encode($row);
} catch (Exception $e) {
    admin_log_error('admin/detail.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server']);
}
