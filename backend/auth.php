<?php
/**
 * JOLATE 2026 — Admin auth module
 *
 * Exposes helpers:
 *   ensure_session()             — starts a session with SameSite=Lax cookie
 *   require_admin()              — exits with 401 JSON if not authenticated
 *   rate_limit_login(...)        — returns lockout state for an IP
 *   rate_limit_record_failure()  — records a failed attempt
 *   log_error($msg)              — append to backend/logs/error.log
 *
 * When invoked directly (as the entry script for /admin/auth.php),
 * dispatches `?action=me|login|logout` and exits.
 */

date_default_timezone_set('UTC');

function admin_log_error($msg) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($dir . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function admin_ensure_session() {
    if (session_id() !== '') {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_rate_limit_state($ip, $pdo, $config) {
    $max    = isset($config['admin']['max_attempts'])   ? (int)$config['admin']['max_attempts']   : 5;
    $window = isset($config['admin']['attempt_window']) ? (int)$config['admin']['attempt_window'] : 300;
    $lock   = isset($config['admin']['lockout_min'])    ? (int)$config['admin']['lockout_min']    : 15;

    // opportunistic cleanup of stale rows (>1 day)
    $cutoff = date('Y-m-d H:i:s', time() - 86400);
    $c = $pdo->prepare("DELETE FROM `jolate_admin_auth_attempts` WHERE failed_at < :c");
    $c->execute([':c' => $cutoff]);

    $since = date('Y-m-d H:i:s', time() - $window);
    $s = $pdo->prepare("SELECT COUNT(*) AS c, MIN(failed_at) AS first FROM `jolate_admin_auth_attempts` WHERE ip = :ip AND failed_at >= :s");
    $s->execute([':ip' => $ip, ':s' => $since]);
    $row = $s->fetch();
    $count = $row ? (int)$row['c'] : 0;
    $first = ($row && !empty($row['first'])) ? strtotime($row['first']) : time();

    if ($count >= $max) {
        $unlockAt = $first + $window + ($lock * 60);
        if (time() < $unlockAt) {
            return ['locked' => true, 'retry_after' => $unlockAt - time()];
        }
    }
    return ['locked' => false, 'retry_after' => 0];
}

function admin_rate_limit_record_failure($ip, $pdo) {
    $s = $pdo->prepare("INSERT INTO `jolate_admin_auth_attempts` (`ip`) VALUES (:ip)");
    $s->execute([':ip' => $ip]);
}

function admin_require() {
    admin_ensure_session();
    if (empty($_SESSION['admin'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

function admin_dispatch() {
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'server_config']);
        exit;
    }
    $config = require $configPath;
    require __DIR__ . '/registrations.php';

    admin_ensure_session();
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['action'] ?? 'me';

    if ($action === 'me') {
        echo json_encode(['authenticated' => !empty($_SESSION['admin'])]);
        return;
    }

    if ($action === 'login' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $user = (is_array($data) && isset($data['user']))     ? (string)$data['user']     : '';
        $pass = (is_array($data) && isset($data['password'])) ? (string)$data['password'] : '';
        $ip   = isset($_SERVER['REMOTE_ADDR'])                ? (string)$_SERVER['REMOTE_ADDR'] : '0.0.0.0';

        try {
            $pdo = get_pdo($config);
        } catch (Exception $e) {
            admin_log_error('auth: db connect failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'code' => 'server']);
            return;
        }

        $rl = admin_rate_limit_state($ip, $pdo, $config);
        if ($rl['locked']) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'code' => 'account_locked', 'retry_after' => (int)$rl['retry_after']]);
            return;
        }

        if ($user === '' || $pass === '') {
            echo json_encode(['ok' => false, 'code' => 'invalid_credentials']);
            return;
        }

        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM `jolate_admins` WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $user]);
        $row = $stmt->fetch();

        $valid = ($row && !empty($row['password_hash'])
                  && password_verify($pass, $row['password_hash']));

        if (!$valid) {
            admin_rate_limit_record_failure($ip, $pdo);
            admin_log_error('auth: login failed user=' . $user . ' ip=' . $ip);
            echo json_encode(['ok' => false, 'code' => 'invalid_credentials']);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id'   => (int)$row['id'],
            'user' => $row['username'],
            'at'   => time(),
        ];
        echo json_encode(['ok' => true]);
        return;
    }

    if ($action === 'logout' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        echo json_encode(['ok' => true]);
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
}

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    admin_dispatch();
    exit;
}
