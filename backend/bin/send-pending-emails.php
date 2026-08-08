#!/usr/bin/env php
<?php
/**
 * JOLATE 2026 — Cron worker: procesa la cola de correos pendientes.
 *
 * Uso: php backend/bin/send-pending-emails.php
 * Se ejecuta cada 5 minutos vía cron (docker/crontab o crontab del server).
 */

if (php_sapi_name() !== 'cli') {
    echo "CLI only.\n";
    exit(1);
}

date_default_timezone_set('UTC');

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    echo "Error: backend/config.php no existe.\n";
    exit(1);
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';
require __DIR__ . '/../mailer.php';

$maxAttempts = isset($config['email_max_attempts']) ? (int) $config['email_max_attempts'] : 5;

try {
    $pdo = get_pdo($config);
} catch (Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] DB connection failed: ' . $e->getMessage() . "\n";
    exit(1);
}

$sql = "SELECT i.*, t.nombre AS rol"
     . " FROM `jolate_inscriptos` i"
     . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
     . " WHERE i.email_part_status = 'pending' OR i.email_comm_status = 'pending'"
     . " ORDER BY i.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();

$uploadDir = rtrim($config['upload_dir'], '/');
$processed = 0;
$failed   = 0;

foreach ($rows as $row) {
    $id    = (int) $row['id'];
    $dni   = $row['dni'];
    $isExpositor = ((int) $row['id_tipo_inscripto'] === 1);
    $pdfPath = ($isExpositor && !empty($row['archivo_filename']))
        ? $uploadDir . '/' . $row['archivo_filename']
        : null;

    // ── Participant email ──
    if ($row['email_part_status'] === 'pending') {
        try {
            sendParticipantEmail($config, $row, $pdfPath);
            $upd = $pdo->prepare("UPDATE `jolate_inscriptos`"
                . " SET `email_part_status` = 'sent',"
                . " `email_part_attempts` = 0,"
                . " `email_part_error` = NULL"
                . " WHERE id = :id");
            $upd->execute([':id' => $id]);
            $processed++;
        } catch (Exception $e) {
            $attempts = (int) $row['email_part_attempts'] + 1;
            $status   = ($attempts >= $maxAttempts) ? 'failed' : 'pending';
            $upd = $pdo->prepare("UPDATE `jolate_inscriptos`"
                . " SET `email_part_status` = :status,"
                . " `email_part_attempts` = :attempts,"
                . " `email_part_error` = :error"
                . " WHERE id = :id");
            $upd->execute([
                ':status'   => $status,
                ':attempts' => $attempts,
                ':error'    => mb_substr($e->getMessage(), 0, 500),
                ':id'       => $id,
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] PART FAILED — id=' . $id
                . ' | attempt=' . $attempts . '/' . $maxAttempts
                . ' | ' . $e->getMessage() . "\n";
            $failed++;
        }
    }

    // ── Committee email ──
    if ($row['email_comm_status'] === 'pending') {
        try {
            sendCommitteeEmail($config, $row, $pdfPath);
            $upd = $pdo->prepare("UPDATE `jolate_inscriptos`"
                . " SET `email_comm_status` = 'sent',"
                . " `email_comm_attempts` = 0,"
                . " `email_comm_error` = NULL"
                . " WHERE id = :id");
            $upd->execute([':id' => $id]);
            $processed++;
        } catch (Exception $e) {
            $attempts = (int) $row['email_comm_attempts'] + 1;
            $status   = ($attempts >= $maxAttempts) ? 'failed' : 'pending';
            $upd = $pdo->prepare("UPDATE `jolate_inscriptos`"
                . " SET `email_comm_status` = :status,"
                . " `email_comm_attempts` = :attempts,"
                . " `email_comm_error` = :error"
                . " WHERE id = :id");
            $upd->execute([
                ':status'   => $status,
                ':attempts' => $attempts,
                ':error'    => mb_substr($e->getMessage(), 0, 500),
                ':id'       => $id,
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] COMM FAILED — id=' . $id
                . ' | attempt=' . $attempts . '/' . $maxAttempts
                . ' | ' . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

if ($processed > 0 || $failed > 0) {
    echo '[' . date('Y-m-d H:i:s') . '] Done: ' . $processed . ' sent, ' . $failed . " failed\n";
}
