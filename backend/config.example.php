<?php
// Copiar este archivo a config.php y completar con tus datos reales.
// config.php debe quedar FUERA del control de versiones (agregarlo a .gitignore)

return array(
    'smtp' => array(
        'host' => 'smtp.tudominio.com',
        'port' => 587,
        'username' => 'notificaciones@tudominio.com',
        'password' => 'TU_PASSWORD_O_APP_PASSWORD',
        'encryption' => 'tls', // 'tls' o 'ssl'
        'from_email' => 'notificaciones@tudominio.com',
        'from_name' => 'Comité Organizador',
    ),

    // A quién le llega el mail con la ponencia
    'committee_emails' => array(
        'comite@tudominio.com',
    ),

    // Carpeta física donde se guardan los PDFs (fuera del webroot si es posible)
    'upload_dir' => __DIR__ . '/uploads/',

    // URL pública desde la que se puede acceder al archivo (para armar el link del mail)
    'public_upload_url' => 'https://tudominio.com/uploads/',

    'max_file_size_mb' => 15,

    // Deben coincidir con las opciones reales del <select> del form
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
