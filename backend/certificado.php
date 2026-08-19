<?php
// Endpoint público de certificados — búsqueda por DNI y descarga de PDF.
//
//   POST certificado.php?action=buscar&dni=...  → JSON con los registros del DNI
//   GET  certificado.php?action=descargar&dni=...&id=... → PDF (genera + cachea)

date_default_timezone_set('UTC');

require __DIR__ . '/registrations.php';
require __DIR__ . '/certificado-lib.php';

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    certificado_error('Configuration missing.', 500, '', 'server_config');
}
$config = require $configPath;

if (!is_array($config) || !isset($config['db']) || !isset($config['certificado_dir'])) {
    certificado_error('Configuration invalid.', 500, '', 'server_config');
}

/**
 * Respuesta de error JSON (o fallback si ya se enviaron headers de PDF).
 */
function certificado_error($mensaje, $status, $field = '', $code = '')
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    $r = ['success' => false, 'error' => $mensaje];
    if ($field !== '') {
        $r['field'] = $field;
    }
    if ($code !== '') {
        $r['code'] = $code;
    }
    echo json_encode($r);
    exit;
}

/**
 * Respuesta JSON de éxito y salida.
 */
function certificado_exit_json(array $data)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

/**
 * Log de errores del endpoint (mismo archivo que el resto del backend).
 */
function certificado_log(string $mensaje): void
{
    $logDir = __DIR__ . '/logs';
    $linea = '[' . date('Y-m-d H:i:s') . '] CERTIFICADO — ' . $mensaje . "\n";
    @file_put_contents($logDir . '/error.log', $linea, FILE_APPEND);
}

/**
 * Rate-limit por IP (archivos en logs/, sin tabla nueva).
 * Máximo 40 solicitudes por hora por bucket y por IP.
 */
function certificado_rate_limit(string $bucket): void
{
    $dir = __DIR__ . '/logs';
    $key = md5($bucket . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $file = $dir . '/rate-' . $key . '.log';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $raw = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $hits = array_values(array_filter($raw, fn($t) => ($now - (int) $t) < 3600));
    }
    if (count($hits) >= 40) {
        certificado_error('Demasiadas solicitudes. Intentá más tarde.', 429, '', 'rate_limited');
    }
    $hits[] = $now;
    @file_put_contents($file, implode("\n", $hits) . "\n");
}

// ---------- Honeypot anti-spam (solo aplica al form POST) ----------
if (!empty($_POST['website'])) {
    certificado_exit_json(['success' => true, 'registros' => []]);
}

// ---------- Acción ----------
$action = $_GET['action'] ?? '';
if (!in_array($action, ['buscar', 'descargar'], true)) {
    certificado_error('Acción no permitida.', 400, '', 'action_invalid');
}

certificado_rate_limit('cert-' . $action);

// ---------- Buscar registros por DNI ----------
if ($action === 'buscar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        certificado_error('Método no permitido.', 405, '', 'method_not_allowed');
    }

    $dni = trim($_POST['dni'] ?? '');
    if (!certificado_dni_valido($dni)) {
        certificado_error('DNI o pasaporte inválido.', 422, 'dni', 'dni_invalid');
    }

    try {
        $pdo = get_pdo($config);
        $stmt = $pdo->prepare(
            'SELECT i.id, t.nombre AS rol, i.nombre, i.institucion,
                    i.titulo_ponencia, i.eje_tematico
               FROM jolate_inscriptos i
               JOIN jolate_tipo_inscripto t ON t.id = i.id_tipo_inscripto
              WHERE i.dni = :dni
           ORDER BY i.created_at, i.id'
        );
        $stmt->execute([':dni' => $dni]);
        $registros = $stmt->fetchAll();
    } catch (PDOException $e) {
        certificado_log('buscar DB: ' . $e->getMessage());
        certificado_error('No se pudo completar la búsqueda.', 500, '', 'server_db');
    }

    foreach ($registros as &$reg) {
        $reg['certificado'] = certificado_existe($dni, (int) $reg['id']);
    }
    unset($reg);

    certificado_exit_json(['success' => true, 'dni' => $dni, 'registros' => $registros]);
}

// ---------- Descargar / generar certificado ----------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    certificado_error('Método no permitido.', 405, '', 'method_not_allowed');
}

$dni = trim($_GET['dni'] ?? '');
$id  = (int) ($_GET['id'] ?? 0);

if (!certificado_dni_valido($dni)) {
    certificado_error('DNI o pasaporte inválido.', 422, 'dni', 'dni_invalid');
}
if ($id <= 0) {
    certificado_error('Registro inválido.', 422, 'id', 'id_invalid');
}

try {
    $pdo = get_pdo($config);
    $stmt = $pdo->prepare(
        'SELECT i.id, t.nombre AS rol, i.nombre, i.institucion,
                i.titulo_ponencia, i.eje_tematico, i.dni
           FROM jolate_inscriptos i
           JOIN jolate_tipo_inscripto t ON t.id = i.id_tipo_inscripto
          WHERE i.id = :id AND i.dni = :dni
          LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':dni' => $dni]);
    $reg = $stmt->fetch();
} catch (PDOException $e) {
    certificado_log('descargar DB: ' . $e->getMessage());
    certificado_error('No se pudo generar el certificado.', 500, '', 'server_db');
}

if (!$reg) {
    certificado_error('No se encontró la inscripción.', 404, '', 'not_found');
}

$rolSlug  = strtolower(preg_replace('/[^A-Za-z]/', '', $reg['rol'] ?? '') ?: 'inscripto');
$filename = 'certificado-' . $rolSlug . '-' . $dni . '.pdf';

// Ya cacheado → servir el archivo; si no → generar, cachear y servir.
$cached = certificado_existe($dni, $id);
if ($cached) {
    $ruta = certificado_cache_path($dni, $id);
} else {
    $pdf = certificado_pdf($reg);
    certificado_guardar($dni, $id, $pdf); // best-effort
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

if ($cached) {
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
} else {
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}
exit;
