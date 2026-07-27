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
    // Para Gmail: host=smtp.gmail.com, port=587, encryption=tls.
    // Generar App Password en https://myaccount.google.com/apppasswords
    // (requiere verificación en 2 pasos activada en la cuenta).
    //
    // Para pruebas con Mailtrap (sandbox):
    // - Crear cuenta gratis en https://mailtrap.io
    // - Crear un sandbox y copiar las credenciales SMTP
    // - Los emails se capturan en el inbox del sandbox (no se envían realmente)
    // - Usar la misma dirección en from_email y committee_emails para pruebas
    'smtp' => array(
        'host'       => 'sandbox.smtp.mailtrap.io',
        'port'       => 2525,
        'username'   => 'd8db494ff9ec45',
        'password'   => '852a4b279830e3',  // Editar con tu password real de Mailtrap
        'encryption' => 'tls',
        'from_email' => 'jolate2026@gmail.com',
        'from_name'  => 'Comité Organizador JOLATE',
    ),

    // ── Destinatarios ─────────────────────────────────────────────
    // Emails que reciben la notificación con la ponencia adjunta.
    // Para pruebas con Mailtrap: usar la misma dirección que from_email
    // para que todos los emails se capturen en el inbox del sandbox.
    'committee_emails' => array(
        'jolate2026@gmail.com',  // Mismo email que from_email para pruebas
    ),

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
