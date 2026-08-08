<?php
/**
 * JOLATE 2026 — Admin: CSV export of all jolate_inscriptos (filtered).
 *
 * UTF-8 BOM + ";" delimiter (Excel es-AR friendly).
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

$rol = isset($_GET['rol']) ? (string)$_GET['rol'] : '';
$q   = isset($_GET['q'])   ? (string)$_GET['q']   : '';

$where = '';
$params = [];
if ($rol === 'Expositor' || $rol === 'Asistente') {
    $where .= ' AND t.nombre = :rol';
    $params[':rol'] = $rol;
}
if ($q !== '') {
    // Single placeholder :q — native prepares (EMULATE_PREPARES=false) reject
    // reusing a named placeholder within the same statement.
    $where .= ' AND CONCAT_WS(\' \', i.nombre, i.institucion, i.email, i.dni, i.titulo_ponencia) LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

try {
    $pdo = get_pdo($config);
    $sql = "SELECT i.id, i.nombre, i.institucion, i.email, i.dni,"
         . " i.titulo_ponencia, i.eje_tematico, i.archivo_filename,"
         . " i.email_part_status, i.email_comm_status,"
         . " i.created_at, t.nombre AS rol"
         . " FROM `jolate_inscriptos` i"
         . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
         . " WHERE 1=1" . $where
         . " ORDER BY i.created_at DESC, i.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $fname = 'jolate_inscriptos-jolate-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: private, no-store');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel detects encoding (acentos correctos).
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Rol', 'Nombre', 'Institución', 'Email', 'DNI',
                        'Título de ponencia', 'Eje temático', '¿Tiene PDF?',
                        'Email Participante', 'Email Comité', 'Fecha de inscripción'],
                  ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['rol'],
            $r['nombre'],
            $r['institucion'],
            $r['email'],
            $r['dni'],
            $r['titulo_ponencia'],
            $r['eje_tematico'],
            !empty($r['archivo_filename']) ? 'Sí' : 'No',
            $r['email_part_status'],
            $r['email_comm_status'],
            $r['created_at'],
        ], ';');
    }
    fclose($out);
} catch (Exception $e) {
    admin_log_error('admin/export_csv.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'server']);
}
