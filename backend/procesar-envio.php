<?php
// Backend processor for JOLATE 2026 paper submissions
// PHP 5.3 compatible — no strict_types, no ??, no random_bytes, no http_response_code

header('Content-Type: application/json; charset=utf-8');

// Load PHPMailer 5.2 via explicit require — composer is NOT available on the hosting
require __DIR__ . '/vendor/phpmailer/class.phpmailer.php';
require __DIR__ . '/vendor/phpmailer/class.smtp.php';

// Load runtime config — guard against missing file to avoid fatal + path exposure
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Configuration missing.'));
    exit;
}
$config = require $configPath;

// Validate config structure — fail loudly and safely, never partially
if (!is_array($config)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Configuration invalid.'));
    exit;
}
$requiredKeys = array('smtp', 'upload_dir', 'committee_emails', 'ejes_tematicos_validos', 'max_file_size_mb');
foreach ($requiredKeys as $k) {
    if (!array_key_exists($k, $config)) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(array('success' => false, 'error' => 'Configuration invalid.'));
        exit;
    }
}
if (!is_array($config['committee_emails']) || count($config['committee_emails']) === 0) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Configuration invalid.'));
    exit;
}
if (!is_writable(dirname($config['upload_dir'])) && !is_writable($config['upload_dir'])) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false, 'error' => 'Upload directory not writable.'));
    exit;
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
 */
function jsonError($mensaje, $status, $field = '') {
    header('HTTP/1.1 ' . $status);
    $respuesta = array('success' => false, 'error' => $mensaje);
    if ($field !== '') {
        $respuesta['field'] = $field;
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

// ---------- Honeypot anti-spam ----------
// A hidden field named "website" should be empty; bots fill it automatically.
if (!empty($_POST['website'])) {
    // Respond with fake success so the bot thinks it worked.
    jsonSuccess('Registro recibido.');
}

// ---------- Request method ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido.', 405);
}

// ---------- Field validation ----------
$nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
$institucion = trim(isset($_POST['institucion']) ? $_POST['institucion'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$eje = trim(isset($_POST['eje_tematico']) ? $_POST['eje_tematico'] : '');

if ($nombre === '' || safeStrlen($nombre) < 3 || safeStrlen($nombre) > 150) {
    jsonError('Nombre completo inválido.', 422, 'nombre');
}

if ($institucion === '' || safeStrlen($institucion) > 200) {
    jsonError('Universidad / Institución inválida.', 422, 'institucion');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Correo electrónico inválido.', 422, 'email');
}

if (!in_array($eje, $config['ejes_tematicos_validos'])) {
    jsonError('Eje temático inválido.', 422, 'eje_tematico');
}

// ---------- PDF validation ----------
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonError('Debes adjuntar el archivo PDF.', 422, 'archivo');
} elseif ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    jsonError('Error al subir el archivo (código ' . $_FILES['archivo']['error'] . ').', 422, 'archivo');
} else {
    $archivo = $_FILES['archivo'];
    $maxBytes = $config['max_file_size_mb'] * 1024 * 1024;

    if ($archivo['size'] > $maxBytes) {
        jsonError('El archivo supera el tamaño máximo permitido (' . $config['max_file_size_mb'] . ' MB).', 422, 'archivo');
    } else {
        // Verify real MIME type via finfo (do not trust browser-provided type)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        if ($mimeReal !== 'application/pdf') {
            jsonError('El archivo debe ser un PDF válido.', 422, 'archivo');
        }
    }
}

// ---------- Secure file storage ----------
// Random filename prevents collisions and enumeration
$bytes = openssl_random_pseudo_bytes(16);
if ($bytes === false) {
    logError('openssl_random_pseudo_bytes() failed — openssl extension may be missing.');
    jsonError('No se pudo generar un nombre de archivo seguro.', 500);
}
$nombreArchivo = bin2hex($bytes) . '.pdf';
$rutaDestino = rtrim($config['upload_dir'], '/') . '/' . $nombreArchivo;

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    $nombreOriginalLog = preg_replace('/[\x00-\x1F\x7F\r\n]/', '', basename($archivo['name']));
    logError('No se pudo guardar el archivo: ' . $nombreOriginalLog);
    jsonError('No se pudo guardar el archivo en el servidor.', 500);
}

// ---------- Email notification ----------
// Sanitize user-supplied name to prevent CR/LF injection in email headers
$nombreSafe = preg_replace('/[\r\n]/', '', $nombre);

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port = $config['smtp']['port'];
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 30;

    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);

    // Send to every configured committee recipient
    foreach ($config['committee_emails'] as $emailDestino) {
        $mail->addAddress($emailDestino);
    }

    $mail->addReplyTo($email, $nombreSafe);

    // Attach the saved PDF to the email
    $mail->addAttachment($rutaDestino, 'ponencia-' . $nombreSafe . '.pdf');

    // Build public download URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    $downloadUrl = $baseUrl . '/uploads/' . $nombreArchivo;

    $mail->isHTML(true);
    $mail->Subject = 'Nueva ponencia recibida: ' . $nombreSafe . ' (' . $eje . ')';
    $mail->Body = '<h2>Nueva ponencia / resumen recibido</h2>'
        . '<p><strong>Nombre:</strong> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Institución:</strong> ' . htmlspecialchars($institucion, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Correo:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Eje temático:</strong> ' . htmlspecialchars($eje, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Archivo:</strong> <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
    $mail->AltBody = 'Nombre: ' . $nombre . "\n"
        . 'Institución: ' . $institucion . "\n"
        . 'Correo: ' . $email . "\n"
        . 'Eje: ' . $eje . "\n"
        . 'Archivo: ' . $downloadUrl;

    $mail->send();
} catch (Exception $e) {
    logError('SMTP FAILED — file kept for manual review: ' . $e->getMessage() . ' | file: ' . $nombreArchivo);
    jsonError('El archivo se guardó pero no se pudo enviar el correo. El administrador fue notificado. No reenvíes el formulario: contactá al comité para confirmar.', 500);
}

jsonSuccess('¡Ponencia recibida correctamente! En breve el comité se pondrá en contacto.');
