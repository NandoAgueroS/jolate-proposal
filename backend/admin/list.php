<?php
/**
 * JOLATE 2026 — Admin: DataTables server-side listing
 *
 * Required: authenticated admin session. All values via PDO placeholders.
 * ORDER BY uses a fixed index→column allow-list; LIMIT/OFFSET use
 * (int) concatenation (native prepared statements reject placeholders
 * in LIMIT/OFFSET).
 */

date_default_timezone_set('UTC');
require __DIR__ . '/../auth.php';
admin_require();

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'server_config']);
    exit;
}
$config = require $configPath;
require __DIR__ . '/../registrations.php';

header('Content-Type: application/json; charset=utf-8');

$draw   = isset($_GET['draw'])   ? (int)$_GET['draw']   : 0;
$start  = isset($_GET['start'])  ? max(0, (int)$_GET['start'])  : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 25;
if ($length < 1)   { $length = 25; }
if ($length > 200) { $length = 200; }

$search = isset($_GET['search']['value']) ? (string)$_GET['search']['value'] : '';
$rol    = isset($_GET['rol'])             ? (string)$_GET['rol']             : '';

$orderColIdx = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
$orderDirRaw = isset($_GET['order'][0]['dir'])    ? strtolower((string)$_GET['order'][0]['dir']) : 'desc';

$cols = [
    0 => 'i.id',
    1 => 't.nombre',
    2 => 'i.nombre',
    3 => 'i.institucion',
    4 => 'i.pais',
    5 => 'i.email',
    6 => 'i.dni',
    7 => 'i.created_at',
    8 => 'i.email_part_status',
    9 => 'i.email_comm_status',
];
$orderCol = $cols[$orderColIdx] ?? 'i.id';
$orderDir = ($orderDirRaw === 'asc') ? 'ASC' : 'DESC';

$where = '';
$params = [];
if ($rol === 'Expositor' || $rol === 'Asistente') {
    $where .= ' AND t.nombre = :rol';
    $params[':rol'] = $rol;
}
if ($search !== '') {
    // Single placeholder :q — native prepares (EMULATE_PREPARES=false) reject
    // reusing a named placeholder within the same statement.
    $where .= ' AND CONCAT_WS(\' \', i.nombre, i.institucion, i.pais, i.trabajo_conjunto, i.email, i.dni, i.titulo_ponencia) LIKE :q';
    $params[':q'] = '%' . $search . '%';
}

try {
    $pdo = get_pdo($config);

    $stmtT = $pdo->prepare("SELECT COUNT(*) AS c FROM `jolate_inscriptos`");
    $stmtT->execute();
    $rowT = $stmtT->fetch();
    $recordsTotal = (int)$rowT['c'];

    $sqlF = "SELECT COUNT(*) AS c FROM `jolate_inscriptos` i"
          . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
          . " WHERE 1=1" . $where;
    $stmtF = $pdo->prepare($sqlF);
    $stmtF->execute($params);
    $rowF = $stmtF->fetch();
    $recordsFiltered = (int)$rowF['c'];

    $sqlD = "SELECT i.id, i.nombre, i.institucion, i.pais, i.trabajo_conjunto, i.email, i.dni,"
          . " i.titulo_ponencia, i.eje_tematico, i.archivo_filename,"
          . " i.email_part_status, i.email_comm_status,"
          . " i.created_at, t.nombre AS rol"
          . " FROM `jolate_inscriptos` i"
          . " JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto"
          . " WHERE 1=1" . $where
          . " ORDER BY " . $orderCol . " " . $orderDir
          . " LIMIT "  . (int)$length . " OFFSET " . (int)$start;
    $stmtD = $pdo->prepare($sqlD);
    $stmtD->execute($params);
    $rows = $stmtD->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id'              => (int)$r['id'],
            'rol'             => $r['rol'],
            'nombre'          => $r['nombre'],
            'institucion'     => $r['institucion'],
            'pais'            => $r['pais'],
            'trabajo_conjunto' => $r['trabajo_conjunto'],
            'email'           => $r['email'],
            'dni'             => $r['dni'],
            'titulo_ponencia' => $r['titulo_ponencia'],
            'eje_tematico'    => $r['eje_tematico'],
            'tiene_pdf'       => !empty($r['archivo_filename']),
            'email_part_status'  => $r['email_part_status'],
            'email_comm_status'  => $r['email_comm_status'],
            'created_at'      => $r['created_at'],
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
} catch (Exception $e) {
    admin_log_error('admin/list.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server']);
}
