<?php
/**
 * JOLATE 2026 — Admin: authenticated PDF download for Expositor submissions.
 *
 * Path-traversal-safe: basename() + realpath() must stay under backend/uploads/.
 * The SameSite cookie is in the output buffer from admin_require(); we flush
 * the buffer before readfile() so the binary stream isn't held in memory.
 */

date_default_timezone_set('UTC');
require __DIR__ . '/../auth.php';
admin_require();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'server_config'));
    exit;
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'bad_id'));
    exit;
}

try {
    $pdo = get_pdo($config);
    $sql = "SELECT archivo_filename, id_tipo_inscripto FROM `inscriptos` WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':id' => $id));
    $row = $stmt->fetch();

    if (!$row || empty($row['archivo_filename']) || (int)$row['id_tipo_inscripto'] !== 1) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'not_found'));
        exit;
    }

    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'server_uploads'));
        exit;
    }

    $cand = $uploadsDir . DIRECTORY_SEPARATOR . basename($row['archivo_filename']);
    $real = realpath($cand);
    if ($real === false || strpos($real, $uploadsDir . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'not_found'));
        exit;
    }

    header('Content-Type: application/pdf');
    $fname = !empty($row['archivo_filename']) ? $row['archivo_filename'] : $id . '.pdf';
    header('Content-Disposition: attachment; filename="ponencia-' . $fname . '"');
    header('Content-Length: ' . filesize($real));
    header('Cache-Control: private, no-store');

    // Flush the output buffer started by admin_require()/ensure_session()
    // so headers are sent to the client and readfile() can stream directly
    // without buffering the (up to 15 MB) PDF in memory.
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    readfile($real);
    exit;
} catch (Exception $e) {
    admin_log_error('admin/download_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => 'server'));
}
