<?php
// Backend processor for JOLATE 2026 paper submissions
// PHP 5.3 compatible — no strict_types, no ??, no random_bytes, no http_response_code

header('Content-Type: application/json; charset=utf-8');
error_log('[procesar-envio] Script started — ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);

// ---------- Config loading ----------
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    error_log('[procesar-envio] Config file not found: ' . $configPath);
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Configuration missing.'));
    exit;
}
$config = require $configPath;
error_log('[procesar-envio] Config loaded successfully');

if (!is_array($config)) {
    error_log('[procesar-envio] Config file returned non-array');
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Configuration invalid.'));
    exit;
}

$requiredKeys = array('upload_dir', 'submissions_dir', 'ejes_tematicos_validos', 'max_file_size_mb', 'worker_token');
foreach ($requiredKeys as $k) {
    if (!array_key_exists($k, $config)) {
        error_log('[procesar-envio] Required config key missing: ' . $k);
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(array('success' => false, 'error' => 'Configuration invalid.'));
        exit;
    }
}

if (!is_writable(dirname($config['upload_dir'])) && !is_writable($config['upload_dir'])) {
    error_log('[procesar-envio] Upload directory not writable: ' . $config['upload_dir']);
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Upload directory not writable.'));
    exit;
}

// Ensure directories exist
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0755, true);
    error_log('[procesar-envio] Created upload directory: ' . $config['upload_dir']);
}
if (!is_dir($config['submissions_dir'])) {
    mkdir($config['submissions_dir'], 0755, true);
    error_log('[procesar-envio] Created submissions directory: ' . $config['submissions_dir']);
}

// ---------- Logging ----------
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/error.log';

function logError($mensaje) {
    global $logFile;
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
    @file_put_contents($logFile, $linea, FILE_APPEND);
}

// ---------- Response helpers ----------
function jsonError($mensaje, $status, $field = '') {
    header('HTTP/1.1 ' . $status);
    $respuesta = array('success' => false, 'error' => $mensaje);
    if ($field !== '') {
        $respuesta['field'] = $field;
    }
    echo json_encode($respuesta);
    exit;
}

function safeStrlen($str) {
    if (extension_loaded('mbstring')) {
        return mb_strlen($str);
    }
    return strlen($str);
}

// ---------- Honeypot anti-spam ----------
if (!empty($_POST['website'])) {
    error_log('[procesar-envio] Honeypot triggered — bot submission blocked');
    echo json_encode(array('success' => true, 'message' => 'Registro recibido.'));
    exit;
}

// ---------- Request method ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('[procesar-envio] Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    jsonError('Método no permitido.', 405);
}

// ---------- Field validation ----------
$nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
$institucion = trim(isset($_POST['institucion']) ? $_POST['institucion'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$eje = trim(isset($_POST['eje_tematico']) ? $_POST['eje_tematico'] : '');

if ($nombre === '' || safeStrlen($nombre) < 3 || safeStrlen($nombre) > 150) {
    error_log('[procesar-envio] Validation failed — nombre: "' . $nombre . '" (len=' . safeStrlen($nombre) . ')');
    jsonError('Nombre completo inválido.', 422, 'nombre');
}

if ($institucion === '' || safeStrlen($institucion) > 200) {
    error_log('[procesar-envio] Validation failed — institucion: "' . $institucion . '" (len=' . safeStrlen($institucion) . ')');
    jsonError('Universidad / Institución inválida.', 422, 'institucion');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log('[procesar-envio] Validation failed — email: "' . $email . '"');
    jsonError('Correo electrónico inválido.', 422, 'email');
}

if (!in_array($eje, $config['ejes_tematicos_validos'])) {
    error_log('[procesar-envio] Validation failed — eje_tematico: "' . $eje . '"');
    jsonError('Eje temático inválido.', 422, 'eje_tematico');
}

// ---------- PDF validation ----------
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
    error_log('[procesar-envio] PDF validation failed — no file uploaded');
    jsonError('Debes adjuntar el archivo PDF.', 422, 'archivo');
} elseif ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    error_log('[procesar-envio] PDF validation failed — upload error code: ' . $_FILES['archivo']['error']);
    jsonError('Error al subir el archivo (código ' . $_FILES['archivo']['error'] . ').', 422, 'archivo');
} else {
    $archivo = $_FILES['archivo'];
    $maxBytes = $config['max_file_size_mb'] * 1024 * 1024;

    if ($archivo['size'] > $maxBytes) {
        error_log('[procesar-envio] PDF validation failed — size ' . $archivo['size'] . ' exceeds ' . $maxBytes);
        jsonError('El archivo supera el tamaño máximo permitido (' . $config['max_file_size_mb'] . ' MB).', 422, 'archivo');
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        if ($mimeReal !== 'application/pdf') {
            error_log('[procesar-envio] PDF validation failed — detected MIME: ' . $mimeReal);
            jsonError('El archivo debe ser un PDF válido.', 422, 'archivo');
        }
    }
}
error_log('[procesar-envio] All validations passed');

// ---------- Secure file storage ----------
$bytes = openssl_random_pseudo_bytes(16);
if ($bytes === false) {
    error_log('[procesar-envio] openssl_random_pseudo_bytes() failed');
    logError('openssl_random_pseudo_bytes() failed — openssl extension may be missing.');
    jsonError('No se pudo generar un nombre de archivo seguro.', 500);
}
$nombreArchivo = bin2hex($bytes) . '.pdf';
$submissionId = bin2hex($bytes);
$rutaDestino = rtrim($config['upload_dir'], '/') . '/' . $nombreArchivo;

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    $nombreOriginalLog = preg_replace('/[\x00-\x1F\x7F\r\n]/', '', basename($archivo['name']));
    error_log('[procesar-envio] move_uploaded_file failed — tmp: ' . $archivo['tmp_name'] . ' | dst: ' . $rutaDestino);
    logError('No se pudo guardar el archivo: ' . $nombreOriginalLog);
    jsonError('No se pudo guardar el archivo en el servidor.', 500);
}
error_log('[procesar-envio] File saved: ' . $nombreArchivo);

// ---------- Write submission record ----------
$submission = array(
    'id' => $submissionId,
    'created_at' => date('Y-m-d H:i:s'),
    'nombre' => $nombre,
    'institucion' => $institucion,
    'email' => $email,
    'eje_tematico' => $eje,
    'archivo' => $nombreArchivo,

    'committee_email_status' => 'pending',
    'committee_email_attempts' => 0,
    'committee_email_last_attempt' => null,
    'committee_email_error' => null,

    'applicant_email_status' => 'pending',
    'applicant_email_attempts' => 0,
    'applicant_email_last_attempt' => null,
    'applicant_email_error' => null,
);

$submissionFile = rtrim($config['submissions_dir'], '/') . '/' . $submissionId . '.json';
$written = @file_put_contents($submissionFile, json_encode($submission));
if ($written === false) {
    error_log('[procesar-envio] Failed to write submission JSON: ' . $submissionFile);
    logError('No se pudo escribir el archivo de postulación: ' . $submissionFile);
    jsonError('No se pudo registrar la postulación en el servidor.', 500);
}
error_log('[procesar-envio] Submission JSON written — ID: ' . $submissionId);

// ---------- Respond to visitor ----------
$responseJson = json_encode(array(
    'success' => true,
    'message' => '¡Ponencia cargada correctamente! El Comité Científico la revisará a la brevedad.',
));
echo $responseJson;

// Allow script to continue after the client disconnects
ignore_user_abort(true);

// Flush output so the browser receives the response immediately
if (ob_get_level() > 0) {
    ob_flush();
}
flush();
error_log('[procesar-envio] Response flushed to client — submission: ' . $submissionId);

// ---------- Auto-trigger worker (asynchronous, fire-and-forget) ----------
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$workerUrl = $scheme . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
    . '/procesar-correos.php?token=' . urlencode($config['worker_token']);

$ctx = stream_context_create(array(
    'http' => array(
        'timeout' => 2,
        'method' => 'GET',
    ),
));
error_log('[procesar-envio] Triggering worker: ' . $workerUrl);
@file_get_contents($workerUrl, false, $ctx);
error_log('[procesar-envio] Worker trigger completed — submission: ' . $submissionId);
exit;
