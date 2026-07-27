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

    // ── Apache ────────────────────────────────────────────────────
    // Versión del servidor Apache que ejecuta este sitio.
    // Opciones: '2.2', '2.4', o 'auto' (detecta automáticamente via .htaccess).
    // Si se cambia a '2.2' o '2.4', los .htaccess se regeneran para esa versión.
    // En modo 'auto', los .htaccess usan <IfModule> para compatibilidad dual.
    'apache_version' => 'auto',

    // ── SMTP (correo de notificación) ─────────────────────────────
    // Configurable via environment variables:
    //   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_ENCRYPTION
    //   SMTP_FROM_EMAIL, SMTP_FROM_NAME
    //
    // Todos los valores deben configurarse via env vars en producción.
    'smtp' => array(
        'host'       => getenv('SMTP_HOST') ?: '',
        'port'       => getenv('SMTP_PORT') ? (int) getenv('SMTP_PORT') : 587,
        'username'   => getenv('SMTP_USER') ?: '',
        'password'   => getenv('SMTP_PASS') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'comite@ejemplo.com',
        'from_name'  => getenv('SMTP_FROM_NAME') ?: 'Comité Organizador JOLATE',
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
    // public_upload_url: URL pública para acceder a los archivos.
    //                    Usada como referencia en el email (informativo).
    'upload_dir'        => __DIR__ . '/uploads/',
    'public_upload_url' => 'https://tudominio.com/uploads/',
    'max_file_size_mb'  => 15,

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
