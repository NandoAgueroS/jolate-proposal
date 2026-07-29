<?php
// Async email worker for JOLATE 2026 paper submissions
// PHP 5.3 compatible — no strict_types, no ??, no random_bytes, no http_response_code
//
// Invocation:
//   HTTP:  GET /backend/procesar-correos.php?token=SECRET
//   CLI:   php backend/procesar-correos.php
//
// Processes pending submissions in batches:
//   1. Sends committee notification email with PDF attached
//   2. Sends applicant confirmation email when committee send succeeds
//   3. Retries up to worker_max_retries times before marking as failed

// ---------- Detect invocation mode ----------
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_log('[procesar-correos] Worker started — mode: ' . ($isCli ? 'CLI' : 'HTTP'));

// ---------- Load dependencies ----------
require __DIR__ . '/vendor/phpmailer/class.phpmailer.php';
require __DIR__ . '/vendor/phpmailer/class.smtp.php';

// ---------- Config loading ----------
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    error_log('[procesar-correos] Config file not found: ' . $configPath);
    if (!$isCli) header('HTTP/1.1 500 Internal Server Error');
    echo 'ERROR: Configuration missing.' . "\n";
    exit(1);
}
$config = require $configPath;
error_log('[procesar-correos] Config loaded successfully');

if (!is_array($config)) {
    error_log('[procesar-correos] Config file returned non-array');
    if (!$isCli) header('HTTP/1.1 500 Internal Server Error');
    echo 'ERROR: Configuration invalid.' . "\n";
    exit(1);
}

$requiredKeys = array(
    'smtp', 'committee_emails', 'upload_dir', 'submissions_dir',
    'upload_url_prefix', 'ejes_tematicos_validos', 'site_url', 'worker_token',
);
foreach ($requiredKeys as $k) {
    if (!array_key_exists($k, $config)) {
        error_log('[procesar-correos] Required config key missing: ' . $k);
        if (!$isCli) header('HTTP/1.1 500 Internal Server Error');
        echo 'ERROR: Configuration key missing: ' . $k . "\n";
        exit(1);
    }
}

// ---------- Token authentication (HTTP only) ----------
if (!$isCli) {
    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
    error_log('[procesar-correos] Token auth — received: ' . ($token ? 'present' : 'missing'));
    if ($token !== $config['worker_token']) {
        error_log('[procesar-correos] Token auth FAILED');
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden';
        exit;
    }
    error_log('[procesar-correos] Token auth OK');
}

// ---------- Self-lock: prevent concurrent worker execution ----------
$lockFile = __DIR__ . '/.worker.lock';
$fp = @fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    error_log('[procesar-correos] Lock not acquired — another instance is running');
    if ($fp) fclose($fp);
    exit(0);
}
error_log('[procesar-correos] Lock acquired');

// ---------- Logging ----------
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/worker.log';

function workerLog($mensaje) {
    global $logFile;
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
    @file_put_contents($logFile, $linea, FILE_APPEND);
}

// ---------- SMTP helpers ----------

/**
 * Send committee notification email for a submission.
 * Returns true on success, or error message string on failure.
 */
function sendCommitteeEmail($config, $submission) {
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp']['username'];
        $mail->Password   = $config['smtp']['password'];
        $mail->SMTPSecure = $config['smtp']['encryption'];
        $mail->Port       = $config['smtp']['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);

        foreach ($config['committee_emails'] as $dest) {
            $mail->addAddress($dest);
        }

        $nombreSafe = preg_replace('/[\r\n]/', '', $submission['nombre']);
        $mail->addReplyTo($submission['email'], $nombreSafe);

        $pdfPath = rtrim($config['upload_dir'], '/') . '/' . $submission['archivo'];
        if (file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath, 'ponencia-' . $nombreSafe . '.pdf');
        } else {
            error_log('[procesar-correos] PDF not found for attachment: ' . $pdfPath);
        }

        $baseUrl = rtrim($config['site_url'], '/');
        $downloadUrl = $baseUrl . $config['upload_url_prefix'] . $submission['archivo'];

        $mail->isHTML(true);
        $mail->Subject = 'Nueva ponencia recibida: ' . $nombreSafe . ' (' . $submission['eje_tematico'] . ')';
        $mail->Body = '<h2>Nueva ponencia / resumen recibido</h2>'
            . '<p><strong>ID de seguimiento:</strong> ' . htmlspecialchars($submission['id'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Nombre:</strong> ' . htmlspecialchars($submission['nombre'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Institución:</strong> ' . htmlspecialchars($submission['institucion'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Correo:</strong> ' . htmlspecialchars($submission['email'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Eje temático:</strong> ' . htmlspecialchars($submission['eje_tematico'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Archivo:</strong> <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';

        $mail->AltBody = 'ID: ' . $submission['id'] . "\n"
            . 'Nombre: ' . $submission['nombre'] . "\n"
            . 'Institución: ' . $submission['institucion'] . "\n"
            . 'Correo: ' . $submission['email'] . "\n"
            . 'Eje: ' . $submission['eje_tematico'] . "\n"
            . 'Archivo: ' . $downloadUrl;

        $mail->send();

        workerLog('Committee email sent OK — submission: ' . $submission['id']);
        error_log('[procesar-correos] Committee email sent OK — submission: ' . $submission['id']);
        return true;

    } catch (Exception $e) {
        $msg = $e->getMessage();
        workerLog('Committee email FAILED — submission: ' . $submission['id'] . ' | error: ' . $msg);
        error_log('[procesar-correos] Committee email FAILED — submission: ' . $submission['id'] . ' | error: ' . $msg);
        return $msg;
    }
}

/**
 * Send confirmation email to the applicant.
 * Returns true on success, or error message string on failure.
 */
function sendApplicantEmail($config, $submission) {
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp']['username'];
        $mail->Password   = $config['smtp']['password'];
        $mail->SMTPSecure = $config['smtp']['encryption'];
        $mail->Port       = $config['smtp']['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;

        $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
        $mail->addAddress($submission['email'], $submission['nombre']);

        $mail->isHTML(true);
        $mail->Subject = 'Confirmación de recepción — JOLATE 2026';

        $mail->Body = '<p>Estimado/a <strong>' . htmlspecialchars($submission['nombre'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Su trabajo ha sido recibido correctamente por nuestro sistema.</p>'
            . '<h3>Datos de su postulación</h3>'
            . '<table style="border-collapse:collapse;width:100%;max-width:500px;">'
            . '<tr><td style="padding:6px 8px;font-weight:bold;border-bottom:1px solid #cbe3e6;">ID de seguimiento</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #cbe3e6;font-family:monospace;">' . htmlspecialchars($submission['id'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:6px 8px;font-weight:bold;border-bottom:1px solid #cbe3e6;">Eje temático</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #cbe3e6;">' . htmlspecialchars($submission['eje_tematico'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:6px 8px;font-weight:bold;border-bottom:1px solid #cbe3e6;">Institución</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #cbe3e6;">' . htmlspecialchars($submission['institucion'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:6px 8px;font-weight:bold;border-bottom:1px solid #cbe3e6;">Archivo recibido</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #cbe3e6;font-family:monospace;">' . htmlspecialchars($submission['archivo'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:6px 8px;font-weight:bold;">Fecha de recepción</td>'
            . '<td style="padding:6px 8px;">' . htmlspecialchars($submission['created_at'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '</table>'
            . '<p>El Comité Científico evaluará su contribución y le comunicará la decisión antes del <strong>18 de septiembre de 2026</strong>.</p>'
            . '<p>Si tiene alguna consulta, puede contactarnos respondiendo este correo o escribiendo a '
            . '<a href="mailto:jolate2026@gmail.com">jolate2026@gmail.com</a>, indicando su ID de seguimiento.</p>'
            . '<p>Atentamente,<br><strong>Comité Organizador JOLATE 2026</strong></p>';

        $mail->AltBody = 'Estimado/a ' . $submission['nombre'] . ",\n\n"
            . "Su trabajo ha sido recibido correctamente por nuestro sistema.\n\n"
            . "Datos de su postulación:\n"
            . "  ID de seguimiento:  " . $submission['id'] . "\n"
            . "  Eje temático:       " . $submission['eje_tematico'] . "\n"
            . "  Institución:        " . $submission['institucion'] . "\n"
            . "  Archivo recibido:   " . $submission['archivo'] . "\n"
            . "  Fecha de recepción: " . $submission['created_at'] . "\n\n"
            . "El Comité Científico evaluará su contribución y le comunicará la decisión antes del 18 de septiembre de 2026.\n\n"
            . "Si tiene alguna consulta, puede contactarnos respondiendo este correo o escribiendo a jolate2026@gmail.com, indicando su ID de seguimiento.\n\n"
            . "Atentamente,\nComité Organizador JOLATE 2026";

        $mail->send();

        workerLog('Applicant email sent OK — submission: ' . $submission['id']);
        error_log('[procesar-correos] Applicant email sent OK — submission: ' . $submission['id']);
        return true;

    } catch (Exception $e) {
        $msg = $e->getMessage();
        workerLog('Applicant email FAILED — submission: ' . $submission['id'] . ' | error: ' . $msg);
        error_log('[procesar-correos] Applicant email FAILED — submission: ' . $submission['id'] . ' | error: ' . $msg);
        return $msg;
    }
}

// ---------- Main: process pending submissions ----------
$submissionsDir = rtrim($config['submissions_dir'], '/');
$batchSize = isset($config['worker_batch_size']) ? (int)$config['worker_batch_size'] : 5;
$maxRetries = isset($config['worker_max_retries']) ? (int)$config['worker_max_retries'] : 5;

error_log('[procesar-correos] Batch config — batch_size: ' . $batchSize . ' | max_retries: ' . $maxRetries);

$files = glob($submissionsDir . '/*.json');
$processed = 0;

if (!$files || count($files) === 0) {
    error_log('[procesar-correos] No submission files found — exiting');
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}
error_log('[procesar-correos] Found ' . count($files) . ' submission file(s) to check');

foreach ($files as $file) {
    if ($processed >= $batchSize) {
        error_log('[procesar-correos] Batch size reached (' . $batchSize . ') — stopping');
        break;
    }

    $data = @json_decode(@file_get_contents($file), true);
    if (!$data || !isset($data['id'])) {
        error_log('[procesar-correos] Skipping malformed file: ' . basename($file));
        continue;
    }

    $submissionId = $data['id'];
    $changed = false;

    // --- Step 1: Send committee notification ---
    if ($data['committee_email_status'] === 'pending') {
        error_log('[procesar-correos] Processing committee email — submission: ' . $submissionId);
        $result = sendCommitteeEmail($config, $data);

        if ($result === true) {
            $data['committee_email_status'] = 'sent';
            error_log('[procesar-correos] Committee email marked sent — submission: ' . $submissionId);
        } else {
            $data['committee_email_attempts'] = (int)$data['committee_email_attempts'] + 1;
            $data['committee_email_last_attempt'] = date('Y-m-d H:i:s');
            $data['committee_email_error'] = $result;
            error_log('[procesar-correos] Committee email attempt ' . $data['committee_email_attempts'] . '/' . $maxRetries . ' failed — submission: ' . $submissionId);
            if ($data['committee_email_attempts'] >= $maxRetries) {
                $data['committee_email_status'] = 'failed';
                workerLog('MAX RETRIES reached for committee email — submission: ' . $data['id']);
                error_log('[procesar-correos] MAX RETRIES reached for committee email — submission: ' . $submissionId);
            }
        }
        $changed = true;
    } else {
        error_log('[procesar-correos] Skipping committee email — status: ' . $data['committee_email_status'] . ' — submission: ' . $submissionId);
    }

    // --- Step 2: Send applicant confirmation (only after committee sent) ---
    if ($data['committee_email_status'] === 'sent' && $data['applicant_email_status'] === 'pending') {
        error_log('[procesar-correos] Processing applicant email — submission: ' . $submissionId);
        $result = sendApplicantEmail($config, $data);

        if ($result === true) {
            $data['applicant_email_status'] = 'sent';
            error_log('[procesar-correos] Applicant email marked sent — submission: ' . $submissionId);
        } else {
            $data['applicant_email_attempts'] = (int)$data['applicant_email_attempts'] + 1;
            $data['applicant_email_last_attempt'] = date('Y-m-d H:i:s');
            $data['applicant_email_error'] = $result;
            error_log('[procesar-correos] Applicant email attempt ' . $data['applicant_email_attempts'] . '/' . $maxRetries . ' failed — submission: ' . $submissionId);
            if ($data['applicant_email_attempts'] >= $maxRetries) {
                $data['applicant_email_status'] = 'failed';
                workerLog('MAX RETRIES reached for applicant email — submission: ' . $data['id']);
                error_log('[procesar-correos] MAX RETRIES reached for applicant email — submission: ' . $submissionId);
            }
        }
        $changed = true;
    } elseif ($data['applicant_email_status'] !== 'pending') {
        error_log('[procesar-correos] Skipping applicant email — status: ' . $data['applicant_email_status'] . ' — submission: ' . $submissionId);
    } else {
        error_log('[procesar-correos] Skipping applicant email — committee not yet sent — submission: ' . $submissionId);
    }

    if ($changed) {
        @file_put_contents($file, json_encode($data));
        $processed++;
        error_log('[procesar-correos] Submission file updated — submission: ' . $submissionId);
    }
}

// ---------- Cleanup ----------
flock($fp, LOCK_UN);
fclose($fp);
error_log('[procesar-correos] Worker finished — processed ' . $processed . ' submission(s)');

if ($isCli) {
    echo 'Worker finished. Processed ' . $processed . ' submissions.' . "\n";
}
exit(0);
