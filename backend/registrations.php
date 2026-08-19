<?php
/**
 * JOLATE 2026 — PDO repository seam for registration persistence.
 *
 * Provides:
 *   get_pdo(array $config)         — returns a PDO connection from the 'db' config block
 *   save_registration(array $data) — inserts a row into `jolate_inscriptos`; returns new id or false
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

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);

    return $pdo;
}

/**
 * Persist a registration row into `jolate_inscriptos`.
 *
 * Expected $data keys:
 *   - id_tipo_inscripto  (int, required)
 *   - nombre             (string, required)
 *   - institucion        (string, required)
 *   - email              (string, required)
 *   - dni                (string, required)
 *   - pais               (string, required — all roles)
 *   - trabajo_conjunto   (string|null, optional — Asistente passes null)
 *   - actividad_principal (string, required)
 *   - titulo_ponencia    (string|null, optional — Asistente passes null)
 *   - eje_tematico       (string|null, optional — Asistente passes null)
 *   - archivo_filename   (string|null, optional — Asistente passes null)
 *   - email_part_status  (string, optional — default 'pending')
 *   - email_part_attempts (int, optional — default 0)
 *   - email_comm_status  (string, optional — default 'pending')
 *   - email_comm_attempts (int, optional — default 0)
 *
 * Uses global $config for DB connection parameters (set by the calling endpoint).
 *
 * @param array $data Associative array of registration data
 * @return int|false  Last insert ID on success, false on failure
 */
function save_registration(array $data) {
    global $config;

    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/error.log';

    try {
        $pdo = get_pdo($config);

        $sql = 'INSERT INTO `jolate_inscriptos` '
             . '(`id_tipo_inscripto`, `nombre`, `institucion`, `email`, `dni`, '
             . '`pais`, `trabajo_conjunto`, `actividad_principal`, `titulo_ponencia`, `eje_tematico`, `archivo_filename`, '
             . '`email_part_status`, `email_part_attempts`, `email_comm_status`, `email_comm_attempts`) '
             . 'VALUES (:id_tipo_inscripto, :nombre, :institucion, :email, :dni, '
             . ':pais, :trabajo_conjunto, :actividad_principal, :titulo_ponencia, :eje_tematico, :archivo_filename, '
             . ':email_part_status, :email_part_attempts, :email_comm_status, :email_comm_attempts)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_tipo_inscripto' => (int) $data['id_tipo_inscripto'],
            ':nombre'            => $data['nombre'],
            ':institucion'       => $data['institucion'],
            ':email'             => $data['email'],
            ':dni'               => $data['dni'],
            ':pais'              => $data['pais']             ?? null,
            ':trabajo_conjunto'  => $data['trabajo_conjunto'] ?? null,
            ':actividad_principal' => $data['actividad_principal'],
            ':titulo_ponencia'   => $data['titulo_ponencia']     ?? null,
            ':eje_tematico'      => $data['eje_tematico']      ?? null,
            ':archivo_filename'  => $data['archivo_filename']  ?? null,
            ':email_part_status' => $data['email_part_status'] ?? 'pending',
            ':email_part_attempts' => (int) ($data['email_part_attempts'] ?? 0),
            ':email_comm_status' => $data['email_comm_status'] ?? 'pending',
            ':email_comm_attempts' => (int) ($data['email_comm_attempts'] ?? 0),
        ]);

        return (int) $pdo->lastInsertId();

    } catch (PDOException $e) {
        $linea = '[' . date('Y-m-d H:i:s') . '] DB FAILED — save_registration: '
               . $e->getMessage() . "\n";
        @file_put_contents($logFile, $linea, FILE_APPEND);
        return false;
    }
}

/**
 * Update email delivery status after send attempt.
 *
 * @param int $id Registration ID
 * @param string $partStatus 'sent', 'pending', or 'failed'
 * @param int $partAttempts Number of send attempts
 * @param string|null $partError Error message (max 500 chars)
 * @param string $commStatus 'sent', 'pending', or 'failed'
 * @param int $commAttempts Number of send attempts
 * @param string|null $commError Error message (max 500 chars)
 * @return bool True on success, false on failure
 */
function update_email_status(int $id, string $partStatus, int $partAttempts, ?string $partError,
                              string $commStatus, int $commAttempts, ?string $commError): bool {
    global $config;

    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/error.log';

    try {
        $pdo = get_pdo($config);

        $sql = 'UPDATE `jolate_inscriptos` '
             . 'SET `email_part_status` = :part_status, '
             . '`email_part_attempts` = :part_attempts, '
             . '`email_part_error` = :part_error, '
             . '`email_comm_status` = :comm_status, '
             . '`email_comm_attempts` = :comm_attempts, '
             . '`email_comm_error` = :comm_error '
             . 'WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':part_status'   => $partStatus,
            ':part_attempts' => $partAttempts,
            ':part_error'    => $partError,
            ':comm_status'   => $commStatus,
            ':comm_attempts' => $commAttempts,
            ':comm_error'    => $commError,
            ':id'            => $id,
        ]);

        return true;

    } catch (PDOException $e) {
        $linea = '[' . date('Y-m-d H:i:s') . '] DB FAILED — update_email_status: '
               . $e->getMessage() . "\n";
        @file_put_contents($logFile, $linea, FILE_APPEND);
        return false;
    }
}
