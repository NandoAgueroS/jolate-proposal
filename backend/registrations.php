<?php
/**
 * JOLATE 2026 — PDO repository seam for registration persistence.
 *
 * PHP 5.3 compatible — no strict_types, no ??, no [] arrays, no random_bytes.
 *
 * Provides:
 *   get_pdo(array $config)         — returns a PDO connection from the 'db' config block
 *   save_registration(array $data) — inserts a row into `inscriptos`; returns new id or false
 */

// Initialize timezone BEFORE any date() call — prevents warnings that corrupt
// HTTP response headers when this file is loaded without procesar-envio.php.
date_default_timezone_set('UTC');

/**
 * Create a PDO connection from the 'db' config block.
 *
 * @param array $config Full config array (must contain 'db' key with host/name/user/pass)
 * @return PDO
 * @throws PDOException on connection failure (ERRMODE_EXCEPTION)
 */
function get_pdo(array $config) {
    $db  = $config['db'];
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $db['user'], $db['pass'], array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));

    return $pdo;
}

/**
 * Persist a registration row into `inscriptos`.
 *
 * Expected $data keys:
 *   - id_tipo_inscripto  (int, required)
 *   - nombre             (string, required)
 *   - institucion        (string, required)
 *   - email              (string, required)
 *   - dni                (string, required)
 *   - titulo_ponencia    (string|null, optional — Asistente passes null)
 *   - eje_tematico       (string|null, optional — Asistente passes null)
 *   - archivo_filename   (string|null, optional — Asistente passes null)
 *
 * Uses global $config for DB connection parameters (set by the calling endpoint).
 *
 * @param array $data Associative array of registration data
 * @return int|false  Last insert ID on success, false on failure
 */
function save_registration(array $data) {
    global $config;

    // Log directory — matches procesar-envio.php convention
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/error.log';

    try {
        $pdo = get_pdo($config);

        // All identifiers are backtick-quoted.
        // The table `tipo inscripto` (with space) is referenced only via FK column
        // `id_tipo_inscripto` — no direct reference in this INSERT.
        $sql = 'INSERT INTO `inscriptos` '
             . '(`id_tipo_inscripto`, `nombre`, `institucion`, `email`, `dni`, '
             . '`titulo_ponencia`, `eje_tematico`, `archivo_filename`) '
             . 'VALUES (:id_tipo_inscripto, :nombre, :institucion, :email, :dni, '
             . ':titulo_ponencia, :eje_tematico, :archivo_filename)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':id_tipo_inscripto' => (int) $data['id_tipo_inscripto'],
            ':nombre'            => $data['nombre'],
            ':institucion'       => $data['institucion'],
            ':email'             => $data['email'],
            ':dni'               => $data['dni'],
            ':titulo_ponencia'   => isset($data['titulo_ponencia'])   ? $data['titulo_ponencia']   : null,
            ':eje_tematico'      => isset($data['eje_tematico'])      ? $data['eje_tematico']      : null,
            ':archivo_filename'  => isset($data['archivo_filename'])  ? $data['archivo_filename']  : null,
        ));

        return (int) $pdo->lastInsertId();

    } catch (PDOException $e) {
        $linea = '[' . date('Y-m-d H:i:s') . '] DB FAILED — save_registration: '
               . $e->getMessage() . "\n";
        @file_put_contents($logFile, $linea, FILE_APPEND);
        return false;
    }
}
