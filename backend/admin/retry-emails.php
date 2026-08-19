<?php
/**
 * JOLATE 2026 — Admin: retry failed/pending emails
 *
 * POST endpoint that reattempts email delivery for registrations
 * with status 'pending' or 'failed'.
 *
 * Params:
 *   scope — 'pending' | 'failed' | 'all'
 *
 * Returns JSON: { ok: true, sent: N, failed: M }
 */

date_default_timezone_set('UTC');
require __DIR__ . '/../auth.php';
admin_require();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'server_config']);
    exit;
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';
require __DIR__ . '/../mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$scope = $_POST['scope'] ?? 'pending';
$statuses = match ($scope) {
    'pending' => ['pending'],
    'failed'  => ['failed'],
    'all'     => ['pending', 'failed'],
    default   => ['pending'],
};

$maxAttempts = isset($config['email_max_attempts']) ? (int) $config['email_max_attempts'] : 5;

try {
    $pdo = get_pdo($config);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connection']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($statuses), '?'));
$sql = "SELECT i.*, t.nombre AS rol"
     . " FROM `jolate_inscriptos` i"
     . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
     . " WHERE i.email_part_status IN ($placeholders) OR i.email_comm_status IN ($placeholders)"
     . " ORDER BY i.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($statuses, $statuses));
$rows = $stmt->fetchAll();

$uploadDir = rtrim($config['upload_dir'], '/');
$sent = 0;
$failed = 0;

foreach ($rows as $row) {
    $id    = (int) $row['id'];
    $dni   = $row['dni'];
    $isExpositor = ((int) $row['id_tipo_inscripto'] === 1);
    $pdfPath = ($isExpositor && !empty($row['archivo_filename']))
        ? $uploadDir . '/' . $row['archivo_filename']
        : null;

    $partStatus = $row['email_part_status'];
    $partAttempts = (int) $row['email_part_attempts'];
    $partError = $row['email_part_error'];

    $commStatus = $row['email_comm_status'];
    $commAttempts = (int) $row['email_comm_attempts'];
    $commError = $row['email_comm_error'];

    $updated = false;

    if (in_array($partStatus, $statuses)) {
        try {
            sendParticipantEmail($config, $row, $pdfPath);
            $partStatus = 'sent';
            $partAttempts = 0;
            $partError = null;
            $sent++;
            $updated = true;
        } catch (Exception $e) {
            $partAttempts++;
            $partStatus = ($partAttempts >= $maxAttempts) ? 'failed' : 'pending';
            $partError = mb_substr($e->getMessage(), 0, 500);
            $failed++;
            $updated = true;
        }
    }

    if (in_array($commStatus, $statuses)) {
        try {
            sendCommitteeEmail($config, $row, $pdfPath);
            $commStatus = 'sent';
            $commAttempts = 0;
            $commError = null;
            $sent++;
            $updated = true;
        } catch (Exception $e) {
            $commAttempts++;
            $commStatus = ($commAttempts >= $maxAttempts) ? 'failed' : 'pending';
            $commError = mb_substr($e->getMessage(), 0, 500);
            $failed++;
            $updated = true;
        }
    }

    if ($updated) {
        $upd = $pdo->prepare("UPDATE `jolate_inscriptos`"
            . " SET `email_part_status` = :part_status,"
            . " `email_part_attempts` = :part_attempts,"
            . " `email_part_error` = :part_error,"
            . " `email_comm_status` = :comm_status,"
            . " `email_comm_attempts` = :comm_attempts,"
            . " `email_comm_error` = :comm_error"
            . " WHERE id = :id");
        $upd->execute([
            ':part_status'   => $partStatus,
            ':part_attempts' => $partAttempts,
            ':part_error'    => $partError,
            ':comm_status'   => $commStatus,
            ':comm_attempts' => $commAttempts,
            ':comm_error'    => $commError,
            ':id'            => $id,
        ]);
    }
}

echo json_encode(['ok' => true, 'sent' => $sent, 'failed' => $failed]);
