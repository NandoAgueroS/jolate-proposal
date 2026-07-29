<?php
/**
 * JOLATE 2026 — Configuración del backend de envío de ponencias
 *
 * Copiar este archivo a config.php y completar con los datos reales del servidor.
 * config.php debe quedar FUERA del control de versiones (.gitignore ya lo excluye).
 *
 * PHP 5.3 compatible — sin sintaxis moderna.
 */

return array(

    // ── SMTP (correo de notificación) ─────────────────────────────
    // Configurable via environment variables:
    //   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION
    //   SMTP_FROM_EMAIL, SMTP_FROM_NAME
    //
    // Todos los valores deben configurarse via env vars en producción.
    'smtp' => array(
        'host'       => getenv('SMTP_HOST'),
        'port'       => getenv('SMTP_PORT'),
        'username'   => getenv('SMTP_USER'),
        'password'   => getenv('SMTP_PASS'),
        'encryption' => getenv('SMTP_ENCRYPTION'),
        'from_email' => getenv('SMTP_FROM_EMAIL'),
        'from_name'  => getenv('SMTP_FROM_NAME'),
    ),

    // ── Destinatarios ─────────────────────────────────────────────
    // Emails que reciben la notificación con la ponencia adjunta.
    // Configurable via SMTP_COMMITTEE_EMAILS (comma-separated).
    'committee_emails' => getenv('SMTP_COMMITTEE_EMAILS')
        ? explode(',', getenv('SMTP_COMMITTEE_EMAILS'))
        : array('comite@ejemplo.com'),

    // ── Almacenamiento de archivos ─────────────────────────────────
    // upload_dir: carpeta física donde se guardan los PDFs.
    //             Debe estar fuera del webroot si es posible.
    'upload_dir'        => __DIR__ . '/uploads/',
    'max_file_size_mb'  => 15,

    // ── Almacenamiento de registros de postulación ─────────────────
    // submissions_dir: carpeta donde se guardan los archivos JSON individuales
    //                  con metadatos y banderas de estado de cada postulante.
    'submissions_dir' => __DIR__ . '/submissions/',

    // ── Prefijo URL público de uploads ─────────────────────────────
    // Ruta URL que se antepone al nombre del archivo para construir
    // el link de descarga en los correos. Debe reflejar la ubicación
    // de upload_dir relativa al webroot del servidor web.
    'upload_url_prefix' => '/backend/uploads/',

    // ── URL pública del sitio ──────────────────────────────────────
    // Usada por el worker para construir links de descarga en los emails.
    // Configurable via SITE_URL.
    'site_url' => getenv('SITE_URL') ?: 'http://localhost:8080',

    // ── Worker asíncrono ───────────────────────────────────────────
    // worker_token:       clave secreta para invocar procesar-correos.php via HTTP.
    // worker_batch_size:  cuántos submissions procesar por cada ejecución.
    // worker_max_retries: reintentos máximos por email antes de marcar como failed.
    'worker_token'       => getenv('WORKER_TOKEN') ?: 'cambiar-este-token',
    'worker_batch_size'  => 5,
    'worker_max_retries' => 5,

    // ── Ejes temáticos válidos ────────────────────────────────────
    // Deben coincidir exactamente con las opciones del <select> del formulario.
    'ejes_tematicos_validos' => array(
        'Teoría de Juegos',
        'Elección Social',
        'Crecimiento Económico',
        'Economía Pública',
        'Equilibrio General',
        'Dinámica Económica',
        'Áreas Temáticas Afines',
    ),
);
