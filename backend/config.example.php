<?php
/**
 * JOLATE 2026 — Configuración del backend de envío de ponencias
 *
 * Copiar este archivo a config.php y completar con los datos reales del servidor.
 * config.php debe quedar FUERA del control de versiones (.gitignore ya lo excluye).
 */

$envPath = DIR . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
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
        'host'       => getenv('SMTP_HOST'),
        'port'       => getenv('SMTP_PORT'),
        'username'   => getenv('SMTP_USER'),
        'password'   => getenv('SMTP_PASS'),
        'encryption' => getenv('SMTP_ENCRYPTION'),
        'from_email' => getenv('SMTP_FROM_EMAIL'),
        'from_name'  => getenv('SMTP_FROM_NAME'),
    ],

    // ── Destinatarios ─────────────────────────────────────────────
    // Emails que reciben la notificación con la ponencia adjunta.
    // Configurable via SMTP_COMMITTEE_EMAILS (comma-separated).
    'committee_emails' => getenv('SMTP_COMMITTEE_EMAILS')
        ? explode(',', getenv('SMTP_COMMITTEE_EMAILS'))
        : ['comite@ejemplo.com'],

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
        'host' => getenv('DB_HOST'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
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
