<?php
// Backend processor for JOLATE 2026 role-based registration (Expositor / Asistente)
// PHP 5.3 compatible — no strict_types, no ??, no random_bytes, no http_response_code

// Initialize timezone BEFORE any date() call — without this, PHP emits warnings that
// corrupt output headers (causing HTTP 200 instead of the intended 500 on error paths).
date_default_timezone_set('UTC');

header('Content-Type: application/json; charset=utf-8');

// Load PHPMailer 6 via explicit require — composer is NOT available on the hosting
require __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/SMTP.php';
require __DIR__ . '/vendor/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// Load PDO repository seam for registration persistence
require __DIR__ . '/registrations.php';

// Load runtime config — guard against missing file to avoid fatal + path exposure
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    jsonError('Configuration missing.', 500, '', 'server_config');
}
$config = require $configPath;

// Validate config structure — fail loudly and safely, never partially
if (!is_array($config)) {
    jsonError('Configuration invalid.', 500, '', 'server_config');
}
$requiredKeys = array(
    'smtp', 'upload_dir', 'committee_emails', 'ejes_tematicos_validos',
    'max_file_size_mb', 'db', 'tipo_inscripto_ids',
);
foreach ($requiredKeys as $k) {
    if (!array_key_exists($k, $config)) {
        jsonError('Configuration invalid.', 500, '', 'server_config');
    }
}
if (!is_array($config['committee_emails']) || count($config['committee_emails']) === 0) {
    jsonError('Configuration invalid.', 500, '', 'server_config');
}
if (!is_array($config['tipo_inscripto_ids']) || count($config['tipo_inscripto_ids']) === 0) {
    jsonError('Configuration invalid.', 500, '', 'server_config');
}
if (!is_writable(dirname($config['upload_dir'])) && !is_writable($config['upload_dir'])) {
    jsonError('Upload directory not writable.', 500, '', 'server_upload_dir');
}

// Ensure upload directory exists
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0755, true);
}

// Ensure log directory exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/error.log';

// Valid roles — must match config['tipo_inscripto_ids'] keys and init.sql seeds
$rolesValidos = array('Expositor', 'Asistente');

/**
 * Append timestamped error to log file
 */
function logError($mensaje) {
    global $logFile;
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
    @file_put_contents($logFile, $linea, FILE_APPEND);
}

/**
 * Return JSON error response and exit
 * $code is a machine-readable key that the frontend maps to a localized message.
 */
function jsonError($mensaje, $status, $field = '', $code = '') {
    header('HTTP/1.1 ' . $status);
    $respuesta = array('success' => false, 'error' => $mensaje);
    if ($field !== '') {
        $respuesta['field'] = $field;
    }
    if ($code !== '') {
        $respuesta['code'] = $code;
    }
    echo json_encode($respuesta);
    exit;
}

/**
 * Return JSON success response and exit
 */
function jsonSuccess($mensaje) {
    echo json_encode(array('success' => true, 'message' => $mensaje));
    exit;
}

/**
 * Safe string length — uses mb_strlen when mbstring is available,
 * falls back to strlen (ASCII-safe for validation purposes).
 */
function safeStrlen($str) {
    if (extension_loaded('mbstring')) {
        return mb_strlen($str);
    }
    return strlen($str);
}

// ---------- Email HTML template helpers ----------
// Corporate palette: primary #055c62, accent #11b0bc, tint #cbe3e6, bg #eef9fa, text #043c41.
// Inline styles only — email clients ignore <style> blocks.

function mailField($label, $valor) {
    return '<p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#055c62;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:2px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#043c41;">'
        . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</p>';
}

function mailWrap($titulo, $contenido, $badge = '') {
    $badgeHtml = '';
    if ($badge !== '') {
        $badgeHtml = '<p style="margin:0 0 18px;"><span style="display:inline-block;background-color:#cbe3e6;color:#055c62;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:999px;">'
            . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span></p>';
    }
    return '<div style="background-color:#eef9fa;padding:24px;">'
        . '<div style="max-width:600px;width:100%;margin:0 auto;background-color:#ffffff;border:1px solid #cbe3e6;border-radius:12px;overflow:hidden;">'
        . '<div style="background-color:#055c62;padding:22px 28px;">'
        . '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#11b0bc;">XXV JOLATE · San Luis, Argentina</p>'
        . '<p style="margin:6px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:bold;color:#ffffff;">'
        . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
        . '<div style="padding:28px;">'
        . $badgeHtml
        . $contenido
        . '</div>'
        . '<div style="background-color:#eef9fa;border-top:1px solid #cbe3e6;padding:14px 28px;">'
        . '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#043c41;">JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica · San Luis, Argentina</p>'
        . '</div>'
        . '</div>'
        . '</div>';
}

// ---------- Honeypot anti-spam ----------
// A hidden field named "website" should be empty; bots fill it automatically.
if (!empty($_POST['website'])) {
    // Respond with fake success so the bot thinks it worked.
    jsonSuccess('Registro recibido.');
}

// ---------- Request method ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405, '', 'method_not_allowed');
}

// ---------- Role validation ----------
$rol = trim(isset($_POST['rol']) ? $_POST['rol'] : '');
if (!in_array($rol, $rolesValidos)) {
    jsonError('Rol inválido. Debe ser Expositor o Asistente.', 422, 'rol', 'rol_invalid');
}

// ---------- Common field validation (all roles) ----------
$nombre      = trim(isset($_POST['nombre'])      ? $_POST['nombre']      : '');
$institucion = trim(isset($_POST['institucion'])  ? $_POST['institucion']  : '');
$email       = trim(isset($_POST['email'])        ? $_POST['email']        : '');
$dni         = trim(isset($_POST['dni'])          ? $_POST['dni']          : '');

if ($nombre === '' || safeStrlen($nombre) < 3 || safeStrlen($nombre) > 150) {
    jsonError('Nombre completo inválido.', 422, 'nombre', 'nombre_invalid');
}

if ($institucion === '' || safeStrlen($institucion) > 200) {
    jsonError('Universidad / Institución inválida.', 422, 'institucion', 'institucion_invalid');
}

if ($dni === '' || safeStrlen($dni) < 5 || safeStrlen($dni) > 20) {
    jsonError('DNI o Pasaporte inválido.', 422, 'dni', 'dni_invalid');
}

if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $dni)) {
    jsonError('DNI o Pasaporte inválido.', 422, 'dni', 'dni_invalid');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 200) {
    jsonError('Correo electrónico inválido.', 422, 'email', 'email_invalid');
}

// ---------- Role-specific field validation ----------
$titulo       = '';
$eje          = '';
$rutaDestino  = null;
$nombreArchivo = null;

if ($rol === 'Expositor') {
    $titulo = trim(isset($_POST['titulo_ponencia']) ? $_POST['titulo_ponencia'] : '');
    $eje    = trim(isset($_POST['eje_tematico'])     ? $_POST['eje_tematico']     : '');

    if ($titulo === '' || safeStrlen($titulo) > 300) {
        jsonError('Título de ponencia inválido.', 422, 'titulo_ponencia', 'titulo_invalid');
    }

    if (!in_array($eje, $config['ejes_tematicos_validos'])) {
        jsonError('Eje temático inválido.', 422, 'eje_tematico', 'eje_invalid');
    }

    // --- PDF validation (Expositor only) ---
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        jsonError('Debes adjuntar el archivo PDF.', 422, 'archivo', 'pdf_missing');
    } elseif ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        // Map PHP upload error codes to machine-readable codes the frontend translates.
        $uploadError = $_FILES['archivo']['error'];
        $uploadErrorCodes = array(
            UPLOAD_ERR_INI_SIZE   => 'upload_ini',
            UPLOAD_ERR_FORM_SIZE  => 'upload_form',
            UPLOAD_ERR_PARTIAL    => 'upload_partial',
            UPLOAD_ERR_NO_TMP_DIR => 'upload_tmp',
            UPLOAD_ERR_CANT_WRITE => 'upload_tmp',
            UPLOAD_ERR_EXTENSION  => 'upload_ext',
        );
        $uploadCode = isset($uploadErrorCodes[$uploadError]) ? $uploadErrorCodes[$uploadError] : 'upload_unknown';
        jsonError('Error al subir el archivo (código ' . $uploadError . ').', 422, 'archivo', $uploadCode);
    } else {
        $archivo  = $_FILES['archivo'];
        $maxBytes = $config['max_file_size_mb'] * 1024 * 1024;

        if ($archivo['size'] > $maxBytes) {
            jsonError('El archivo supera el tamaño máximo permitido (' . $config['max_file_size_mb'] . ' MB).', 422, 'archivo', 'pdf_too_large');
        }

        // Verify real MIME type via finfo (do not trust browser-provided type)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        if ($mimeReal !== 'application/pdf') {
            jsonError('El archivo debe ser un PDF válido.', 422, 'archivo', 'pdf_invalid');
        }
    }
} elseif ($rol === 'Asistente') {
    // Asistente MUST NOT send paper fields — reject with 422 if any are present
    $hasTitulo  = trim(isset($_POST['titulo_ponencia']) ? $_POST['titulo_ponencia'] : '');
    $hasEje     = trim(isset($_POST['eje_tematico'])    ? $_POST['eje_tematico']    : '');
    $hasArchivo = (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE);

    if ($hasTitulo !== '' || $hasEje !== '' || $hasArchivo) {
        jsonError('El rol Asistente no admite campos de ponencia (titulo_ponencia, eje_tematico, archivo).', 422, '', 'asistente_fields');
    }
}

// ---------- Resolve id_tipo_inscripto from config map ----------
if (!isset($config['tipo_inscripto_ids'][$rol])) {
    logError('tipo_inscripto_ids map missing role: ' . $rol);
    jsonError('Error de configuración de rol.', 500, '', 'server_role_map');
}
$idTipoInscripto = (int) $config['tipo_inscripto_ids'][$rol];

// ---------- Expositor: secure file storage ----------
// Random filename prevents collisions and enumeration
if ($rol === 'Expositor') {
    $bytes = openssl_random_pseudo_bytes(16);
    if ($bytes === false) {
        logError('openssl_random_pseudo_bytes() failed — openssl extension may be missing.');
        jsonError('No se pudo generar un nombre de archivo seguro.', 500, '', 'server_file_name');
    }
    $nombreArchivo = $dni . '-' . bin2hex($bytes) . '.pdf';
    $rutaDestino   = rtrim($config['upload_dir'], '/') . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        $nombreOriginalLog = preg_replace('/[\x00-\x1F\x7F\r\n]/', '', basename($archivo['name']));
        logError('No se pudo guardar el archivo: ' . $nombreOriginalLog);
        jsonError('No se pudo guardar el archivo. Intentá nuevamente.', 500, '', 'server_move');
    }
}

// ---------- Persistence ----------
$registrationData = array(
    'id_tipo_inscripto' => $idTipoInscripto,
    'nombre'            => $nombre,
    'institucion'       => $institucion,
    'email'             => $email,
    'dni'               => $dni,
);

if ($rol === 'Expositor') {
    $registrationData['titulo_ponencia']  = $titulo;
    $registrationData['eje_tematico']     = $eje;
    $registrationData['archivo_filename'] = $nombreArchivo;
}

$idInscripto = save_registration($registrationData);

if ($idInscripto === false) {
    // DB failure: best-effort unlink the saved PDF (Expositor only)
    if ($rol === 'Expositor' && $rutaDestino !== null && file_exists($rutaDestino)) {
        @unlink($rutaDestino);
    }
    logError('DB FAILED — save_registration returned false for ' . $rol . ' / ' . $email);
    jsonError('No se pudo registrar la inscripción. Intentá más tarde.', 500, '', 'server_db');
}

// ---------- Dual email notification ----------
// Sanitize user-supplied name to prevent CR/LF injection in email headers
$nombreSafe = preg_replace('/[\r\n]/', '', $nombre);

// --- 1) Participant confirmation email ---
try {
    $mailParticipante = new PHPMailer(true);
    $mailParticipante->isSMTP();
    $mailParticipante->Host       = $config['smtp']['host'];
    $mailParticipante->SMTPAuth   = true;
    $mailParticipante->Username   = $config['smtp']['username'];
    $mailParticipante->Password   = $config['smtp']['password'];
    $mailParticipante->SMTPSecure = $config['smtp']['encryption'];
    $mailParticipante->Port       = $config['smtp']['port'];
    $mailParticipante->CharSet    = 'UTF-8';
    $mailParticipante->Timeout    = 30;

    $mailParticipante->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mailParticipante->addAddress($email, $nombreSafe);
    $mailParticipante->isHTML(true);

    if ($rol === 'Expositor') {
        // Attach the saved PDF so the participant keeps a copy of their paper
        $mailParticipante->addAttachment($rutaDestino, 'ponencia-' . $dni . '.pdf');

        $mailParticipante->Subject = 'Confirmación de recepción de ponencia — JOLATE 2026';
        $mailParticipante->Body    = mailWrap(
            'Tu ponencia fue recibida correctamente',
            mailField('Nombre', $nombre)
            . mailField('Eje temático', $eje)
            . mailField('Título de la ponencia', $titulo)
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">Tu ponencia se adjunta a este correo.</p>'
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">En breve el comité se pondrá en contacto.</p>',
            'Expositor'
        );
        $mailParticipante->AltBody = 'Tu ponencia fue recibida correctamente.' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Rol: Expositor' . "\n"
            . 'Eje: ' . $eje . "\n"
            . 'Título: ' . $titulo . "\n"
            . 'Archivo: adjunto a este correo' . "\n"
            . 'En breve el comité se pondrá en contacto.';
    } else {
        $mailParticipante->Subject = 'Confirmación de inscripción — JOLATE 2026';
        $mailParticipante->Body    = mailWrap(
            'Tu inscripción fue recibida correctamente',
            mailField('Nombre', $nombre)
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">En breve el comité se pondrá en contacto.</p>',
            'Asistente'
        );
        $mailParticipante->AltBody = 'Tu inscripción fue recibida correctamente.' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Rol: Asistente' . "\n"
            . 'En breve el comité se pondrá en contacto.';
    }

    $mailParticipante->send();
} catch (Exception $e) {
    logError('SMTP PARTICIPANT FAILED — record kept for manual review: ' . $e->getMessage()
        . ' | rol: ' . $rol . ' | email: ' . $email
        . ($nombreArchivo !== null ? ' | file: ' . $nombreArchivo : ''));
    jsonError('La inscripción se registró pero no se pudo enviar el correo de confirmación. No reenvíes el formulario: contactá al comité para confirmar.', 500, '', 'server_smtp_participant');
}

// --- 2) Committee notification email (all SMTP_COMMITTEE_EMAILS recipients) ---
try {
    $mailComite = new PHPMailer(true);
    $mailComite->isSMTP();
    $mailComite->Host       = $config['smtp']['host'];
    $mailComite->SMTPAuth   = true;
    $mailComite->Username   = $config['smtp']['username'];
    $mailComite->Password   = $config['smtp']['password'];
    $mailComite->SMTPSecure = $config['smtp']['encryption'];
    $mailComite->Port       = $config['smtp']['port'];
    $mailComite->CharSet    = 'UTF-8';
    $mailComite->Timeout    = 30;

    $mailComite->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);

    // Send to every configured committee recipient
    foreach ($config['committee_emails'] as $emailDestino) {
        $mailComite->addAddress($emailDestino);
    }
    $mailComite->addReplyTo($email, $nombreSafe);
    $mailComite->isHTML(true);

    if ($rol === 'Expositor') {
        // Attach the saved PDF to the committee notification
        $mailComite->addAttachment($rutaDestino, 'ponencia-' . $dni . '.pdf');

        $mailComite->Subject = 'Nueva ponencia recibida: ' . $nombreSafe . ' (' . $eje . ')';
        $mailComite->Body    = mailWrap(
            'Nueva ponencia / resumen recibido',
            mailField('Nombre', $nombre)
            . mailField('DNI / Pasaporte', $dni)
            . mailField('Institución', $institucion)
            . mailField('Correo', $email)
            . mailField('Eje temático', $eje)
            . mailField('Título de la ponencia', $titulo)
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">La ponencia se adjunta a este correo.</p>',
            'Expositor'
        );
        $mailComite->AltBody = 'Nueva ponencia recibida' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'DNI / Pasaporte: ' . $dni . "\n"
            . 'Institución: ' . $institucion . "\n"
            . 'Correo: ' . $email . "\n"
            . 'Rol: Expositor' . "\n"
            . 'Eje: ' . $eje . "\n"
            . 'Título: ' . $titulo . "\n"
            . 'Archivo: adjunto a este correo';
    } else {
        $mailComite->Subject = 'Nueva inscripción: ' . $nombreSafe . ' (Asistente)';
        $mailComite->Body    = mailWrap(
            'Nueva inscripción',
            mailField('Nombre', $nombre)
            . mailField('Institución', $institucion)
            . mailField('Correo', $email),
            'Asistente'
        );
        $mailComite->AltBody = 'Nueva inscripción' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Institución: ' . $institucion . "\n"
            . 'Correo: ' . $email . "\n"
            . 'Rol: Asistente';
    }

    $mailComite->send();
} catch (Exception $e) {
    logError('SMTP COMMITTEE FAILED — record kept for manual review: ' . $e->getMessage()
        . ' | rol: ' . $rol . ' | email: ' . $email
        . ($nombreArchivo !== null ? ' | file: ' . $nombreArchivo : ''));
    jsonError('La inscripción se registró pero no se pudo enviar la notificación al comité. No reenvíes el formulario: contactá al comité para confirmar.', 500, '', 'server_smtp_committee');
}

// ---------- Success ----------
if ($rol === 'Expositor') {
    jsonSuccess('¡Ponencia recibida correctamente! En breve el comité se pondrá en contacto.');
} else {
    jsonSuccess('¡Inscripción recibida correctamente! En breve el comité se pondrá en contacto.');
}
