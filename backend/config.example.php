<?php
/**
 * JOLATE 2026 — Configuración del backend de envío de ponencias
 *
 * Copiar este archivo a config.php y completar con los datos reales del servidor.
 * config.php debe quedar FUERA del control de versiones (.gitignore ya lo excluye).
 */

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

/**
 * Verifica que los directorios de runtime existan y sean utilizables.
 * La creación y el cambio de propietario/permisos se resuelven fuera de PHP.
 */
function jolate_verify_runtime_directory(string $path): void
{
    $requiredMode = 0755;

    if (!is_dir($path)) {
        jolate_log('Falta el directorio de runtime: ' . $path);
        return;
    }

    $permissions = fileperms($path);
    if ($permissions === false) {
        jolate_log('No se pudieron verificar los permisos de: ' . $path);
        return;
    }

    $actualMode = $permissions & 0777;
    $missingMode = $requiredMode & ~$actualMode;
    if ($missingMode !== 0) {
        jolate_log(sprintf(
            'Faltan permisos en %s: requeridos 0%o, actuales 0%o',
            $path,
            $requiredMode,
            $actualMode
        ));
    }

    if (!is_readable($path)) {
        jolate_log('El proceso PHP no tiene permiso de lectura en: ' . $path);
    }
    if (!is_writable($path)) {
        jolate_log('El proceso PHP no tiene permiso de escritura en: ' . $path);
    }
    if (!is_executable($path)) {
        jolate_log('El proceso PHP no tiene permiso de acceso en: ' . $path);
    }
}

/**
 * Append timestamped error to logs/error.log (same format as other backend files).
 */
function jolate_log(string $msg): void
{
    $dir = __DIR__ . '/logs';
    $linea = '[' . date('Y-m-d H:i:s') . '] [CONFIG] — ' . $msg . "\n";
    @file_put_contents($dir . '/error.log', $linea, FILE_APPEND);
}

jolate_verify_runtime_directory(__DIR__ . '/uploads');
jolate_verify_runtime_directory(__DIR__ . '/logs');
jolate_verify_runtime_directory(__DIR__ . '/certificados');

// ── Variables de entorno requeridas ────────────────────────────────
// Todas las variables que lee este config son obligatorias.
$requiredEnv = [
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_ENCRYPTION',
    'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME', 'SMTP_COMMITTEE_EMAILS',
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
];
foreach ($requiredEnv as $key) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
        jolate_log("Falta la variable de entorno requerida: $key");
    }
}

return [

    // ── SMTP (correo de notificación) ─────────────────────────────
    // Configurable via environment variables:
    //   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION
    //   SMTP_FROM_EMAIL, SMTP_FROM_NAME
    //
    // Todos los valores deben configurarse via env vars en producción.
    'smtp' => [
        'host'       => $_ENV['SMTP_HOST'],
        'port'       => $_ENV['SMTP_PORT'],
        'username'   => $_ENV['SMTP_USER'],
        'password'   => $_ENV['SMTP_PASS'],
        'encryption' => $_ENV['SMTP_ENCRYPTION'],
        'from_email' => $_ENV['SMTP_FROM_EMAIL'],
        'from_name'  => $_ENV['SMTP_FROM_NAME'],
    ],

    // ── Destinatarios ─────────────────────────────────────────────
    // Emails que reciben la notificación con la ponencia adjunta.
    // Configurable via SMTP_COMMITTEE_EMAILS (comma-separated).
    'committee_emails' => explode(',', $_ENV['SMTP_COMMITTEE_EMAILS']),

    // ── Almacenamiento de archivos ─────────────────────────────────
    // upload_dir: carpeta física donde se guardan los PDFs.
    //             Debe estar fuera del webroot si es posible.
    'upload_dir'        => __DIR__ . '/uploads/',
    'max_file_size_mb'  => 7,

    // ── Certificados ───────────────────────────────────────────────
    // certificado_dir: carpeta física donde se cachean los certificados
    // generados (backend/certificados/{dni}/{id}.pdf).
    // Bloqueada por .htaccess — solo se sirve vía certificado.php.
    'certificado_dir'   => __DIR__ . '/certificados/',

    // ── Ejes temáticos válidos ────────────────────────────────────
    // Deben coincidir exactamente con las opciones del <select> del formulario.
    'ejes_tematicos_validos' => [
        'Teoría de Juegos',
        'Elección Social',
        'Crecimiento Económico',
        'Economía Pública',
        'Equilibrio General',
        'Dinámica Económica',
        'Áreas Temáticas Afines',
    ],

    // ── Base de datos (MySQL 8) ────────────────────────────────
    // Configurable via environment variables:
    //   DB_HOST, DB_NAME, DB_USER, DB_PASS
    'db' => [
        'host' => $_ENV['DB_HOST'],
        'name' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'pass' => $_ENV['DB_PASS'],
    ],

    // ── Tipo de inscripto ──────────────────────────────────────────
    // Mapa rol (POST) → id en la tabla `jolate_tipo_inscripto`.
    // Debe coincidir con las semillas de docker/database/init.sql.
    'tipo_inscripto_ids' => [
        'Expositor'  => 1,
        'Asistente'  => 2,
    ],

    // ── Admin (rate-limit del login) ───────────────────────────────
    // Las credenciales viven en la tabla `jolate_admins` de la DB.
    // max_attempts:   cantidad de fallos permitidos dentro de attempt_window
    // attempt_window: ventana móvil en segundos
    // lockout_min:    minutos de bloqueo tras alcanzar max_attempts
    'admin' => [
        'max_attempts'   => 5,
        'attempt_window' => 300,
        'lockout_min'    => 15,
    ],

    // ── Email worker ──────────────────────────────────────────────
    // Cantidad máxima de reintentos por tipo de email antes de marcar
    // como 'failed'. El worker de cron (send-pending-emails.php)
    // incrementa el contador en cada intento fallido.
    'email_max_attempts' => 5,
];
