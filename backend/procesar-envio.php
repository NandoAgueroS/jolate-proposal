<?php
// Backend processor for JOLATE 2026 role-based registration (Expositor / Asistente)

// Initialize timezone BEFORE any date() call — without this, PHP emits warnings that
// corrupt output headers (causing HTTP 200 instead of the intended 500 on error paths).
date_default_timezone_set('UTC');

header('Content-Type: application/json; charset=utf-8');

// Load PDO repository seam for registration persistence
require __DIR__ . '/registrations.php';
require __DIR__ . '/mailer.php';

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
$requiredKeys = [
    'smtp', 'upload_dir', 'committee_emails', 'ejes_tematicos_validos',
    'max_file_size_mb', 'db', 'tipo_inscripto_ids',
];
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
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/error.log';

// Valid roles — must match config['tipo_inscripto_ids'] keys and init.sql seeds
$rolesValidos = ['Expositor', 'Asistente'];

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
    http_response_code($status);
    $respuesta = ['success' => false, 'error' => $mensaje];
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
    echo json_encode(['success' => true, 'message' => $mensaje]);
    exit;
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
$rol = trim($_POST['rol'] ?? '');
if (!in_array($rol, $rolesValidos)) {
    jsonError('Rol inválido. Debe ser Expositor o Asistente.', 422, 'rol', 'rol_invalid');
}

// ---------- Common field validation (all roles) ----------
$nombre      = trim($_POST['nombre']      ?? '');
$institucion = trim($_POST['institucion'] ?? '');
$email       = trim($_POST['email']       ?? '');
$dni         = trim($_POST['dni']         ?? '');

if ($nombre === '' || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
    jsonError('Nombre completo inválido.', 422, 'nombre', 'nombre_invalid');
}

if ($institucion === '' || mb_strlen($institucion) > 200) {
    jsonError('Universidad / Institución inválida.', 422, 'institucion', 'institucion_invalid');
}

if ($dni === '' || mb_strlen($dni) < 5 || mb_strlen($dni) > 20) {
    jsonError('DNI o Pasaporte inválido.', 422, 'dni', 'dni_invalid');
}

if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $dni)) {
    jsonError('DNI o Pasaporte inválido.', 422, 'dni', 'dni_invalid');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
    jsonError('Correo electrónico inválido.', 422, 'email', 'email_invalid');
}

$actividadPrincipal = trim($_POST['actividad_principal'] ?? '');
if ($actividadPrincipal === '' || mb_strlen($actividadPrincipal) > 60) {
    jsonError('Actividad principal inválida.', 422, 'actividad_principal', 'actividad_invalid');
}

$pais = trim($_POST['pais'] ?? '');
if ($pais === '' || mb_strlen($pais) > 100) {
    jsonError('País inválido.', 422, 'pais', 'pais_invalid');
}

// ---------- Role-specific field validation ----------
$titulo       = '';
$eje          = '';
$rutaDestino  = null;
$nombreArchivo = null;

if ($rol === 'Expositor') {
    $titulo = trim($_POST['titulo_ponencia'] ?? '');
    $eje    = trim($_POST['eje_tematico']    ?? '');
    $trabajoConjunto = trim($_POST['trabajo_conjunto'] ?? '');

    if ($titulo === '' || mb_strlen($titulo) > 300) {
        jsonError('Título de la presentación inválido.', 422, 'titulo_ponencia', 'titulo_invalid');
    }

    if (mb_strlen($trabajoConjunto) > 300) {
        jsonError('Trabajo en conjunto con inválido.', 422, 'trabajo_conjunto', 'trabajo_conjunto_invalid');
    }

    if (!in_array($eje, $config['ejes_tematicos_validos'])) {
        jsonError('Eje temático inválido.', 422, 'eje_tematico', 'eje_invalid');
    }

    // --- PDF validation (Expositor only) ---
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        jsonError('Debés adjuntar el resumen PDF.', 422, 'archivo', 'pdf_missing');
    } elseif ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        // Map PHP upload error codes to machine-readable codes the frontend translates.
        $uploadError = $_FILES['archivo']['error'];
        $uploadErrorCodes = [
            UPLOAD_ERR_INI_SIZE   => 'upload_ini',
            UPLOAD_ERR_FORM_SIZE  => 'upload_form',
            UPLOAD_ERR_PARTIAL    => 'upload_partial',
            UPLOAD_ERR_NO_TMP_DIR => 'upload_tmp',
            UPLOAD_ERR_CANT_WRITE => 'upload_tmp',
            UPLOAD_ERR_EXTENSION  => 'upload_ext',
        ];
        $uploadCode = $uploadErrorCodes[$uploadError] ?? 'upload_unknown';
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
    $hasTitulo        = trim($_POST['titulo_ponencia']     ?? '');
    $hasEje           = trim($_POST['eje_tematico']        ?? '');
    $hasTrabajoConjunto = trim($_POST['trabajo_conjunto']  ?? '');
    $hasArchivo = (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE);

    if ($hasTitulo !== '' || $hasEje !== '' || $hasTrabajoConjunto !== '' || $hasArchivo) {
        jsonError('El rol Asistente no admite campos de la presentación (titulo_ponencia, eje_tematico, archivo, trabajo_conjunto).', 422, '', 'asistente_fields');
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
    $bytes = random_bytes(16);
    $nombreArchivo = $dni . '-' . bin2hex($bytes) . '.pdf';
    $rutaDestino   = rtrim($config['upload_dir'], '/') . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        $nombreOriginalLog = preg_replace('/[\x00-\x1F\x7F\r\n]/', '', basename($archivo['name']));
        logError('No se pudo guardar el archivo: ' . $nombreOriginalLog);
        jsonError('No se pudo guardar el archivo. Intentá nuevamente.', 500, '', 'server_move');
    }
}

// ---------- Persistence ----------
$registrationData = [
    'id_tipo_inscripto'  => $idTipoInscripto,
    'nombre'             => $nombre,
    'institucion'        => $institucion,
    'pais'               => $pais,
    'email'              => $email,
    'dni'                => $dni,
    'actividad_principal' => $actividadPrincipal,
];

if ($rol === 'Expositor') {
    $registrationData['trabajo_conjunto'] = $trabajoConjunto !== '' ? $trabajoConjunto : null;
    $registrationData['titulo_ponencia']  = $titulo;
    $registrationData['eje_tematico']     = $eje;
    $registrationData['archivo_filename'] = $nombreArchivo;
}

$idInscripto = save_registration($registrationData);

if ($idInscripto === false) {
    if ($rol === 'Expositor' && $rutaDestino !== null && file_exists($rutaDestino)) {
        @unlink($rutaDestino);
    }
    logError('DB FAILED — save_registration returned false for ' . $rol . ' / ' . $email);
    jsonError('No se pudo registrar la inscripción. Intentá más tarde.', 500, '', 'server_db');
}

// ---------- Email sending (best-effort) ----------
$partStatus   = 'pending';
$partAttempts = 0;
$partError    = null;
$commStatus   = 'pending';
$commAttempts = 0;
$commError    = null;

$mailerRow = [
    'id_tipo_inscripto'  => $idTipoInscripto,
    'nombre'             => $nombre,
    'email'              => $email,
    'dni'                => $dni,
    'pais'               => $pais,
    'institucion'        => $institucion,
    'actividad_principal' => $actividadPrincipal,
    'trabajo_conjunto'   => $rol === 'Expositor' ? ($trabajoConjunto ?? null) : null,
    'titulo_ponencia'    => $rol === 'Expositor' ? $titulo : null,
    'eje_tematico'       => $rol === 'Expositor' ? $eje : null,
];

$pdfPath = ($rol === 'Expositor' && $rutaDestino !== null) ? $rutaDestino : null;

try {
    sendParticipantEmail($config, $mailerRow, $pdfPath);
    $partStatus = 'sent';
} catch (Exception $e) {
    $partAttempts = 1;
    $partError = mb_substr($e->getMessage(), 0, 500);
    logError('PART EMAIL FAILED id=' . $idInscripto . ': ' . $e->getMessage());
}

try {
    sendCommitteeEmail($config, $mailerRow, $pdfPath);
    $commStatus = 'sent';
} catch (Exception $e) {
    $commAttempts = 1;
    $commError = mb_substr($e->getMessage(), 0, 500);
    logError('COMM EMAIL FAILED id=' . $idInscripto . ': ' . $e->getMessage());
}

update_email_status($idInscripto, $partStatus, $partAttempts, $partError,
                    $commStatus, $commAttempts, $commError);

// ---------- Success ----------
jsonSuccess('¡Inscripción registrada correctamente! Recibirás un correo de confirmación en breve.');
