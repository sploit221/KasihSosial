<?php

// ─── 1. SESSION HARDENING ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'httponly' => true,                       // cegah akses JS ke cookie
        'samesite' => 'Strict',                  // cegah CSRF via cookie
    ]);
    
    session_start();
    
    // Regenerasi session ID untuk cegah session fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

// ─── 2. SECURITY HEADERS ──────────────────────────────────────────────────────
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
    // Content Security Policy — sesuaikan domain CDN yang dipakai
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data: https://placehold.co https://www.google.com; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self';"
    );
}

// ─── 3. DATABASE CONNECTION ───────────────────────────────────────────────────

$host = "localhost";
$user = "root";
$pass = "";
$db   = "kasihsosial";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Aktifkan laporan error MySQLi SEBELUM koneksi agar exception tertangkap
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db,);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("[KasihSosial] DB Error: " . $e->getMessage());
    http_response_code(503);
    // Tampilkan halaman error yang ramah, bukan raw JSON / stack trace
    $isJson = (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
    if ($isJson) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Layanan sementara tidak tersedia. Coba beberapa saat lagi.']));
    }
    die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<title>Koneksi Gagal — KasihSosial</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center p-5">
  <div style="font-size:3rem;">⚠️</div>
  <h4 class="mt-3 fw-bold">Server sedang bermasalah</h4>
  <p class="text-muted">Koneksi ke database gagal. Pastikan <strong>MySQL sudah dijalankan</strong> di XAMPP Control Panel.</p>
  <a href="javascript:location.reload()" class="btn btn-primary mt-2">Coba Lagi</a>
</div>
</body></html>');
}

// ─── 4. HELPER FUNCTIONS ──────────────────────────────────────────────────────

/** Sanitasi output untuk mencegah XSS */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Sanitasi input teks */
if (!function_exists('sanitize')) {
    function sanitize(string $input, int $maxLen = 500): string {
        return mb_substr(trim(strip_tags($input)), 0, $maxLen);
    }
}

/** Validasi integer positif */
if (!function_exists('validateId')) {
    function validateId($val): int {
        $id = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? 0 : (int)$id;
    }
}

/** Validasi enum terhadap whitelist */
if (!function_exists('validateEnum')) {
    function validateEnum($val, array $allowed): ?string {
        return in_array($val, $allowed, true) ? $val : null;
    }
}

// ─── 5. CSRF PROTECTION ───────────────────────────────────────────────────────
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Output hidden CSRF input field */
if (!function_exists('csrfField')) {
    function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
    }
}

// ─── 6. RATE LIMITING (session-based, tanpa Redis) ────────────────────────────
if (!function_exists('rateLimit')) {
    /**
     * @param string $action  Nama aksi (e.g. 'login', 'send_chat')
     * @param int    $max     Maksimum percobaan
     * @param int    $window  Jendela waktu dalam detik
     */
    function rateLimit(string $action, int $max = 5, int $window = 60): bool {
        $key = "_rl_{$action}";
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => $now];
        }

        // Reset window jika sudah lewat
        if ($now - $_SESSION[$key]['start'] > $window) {
            $_SESSION[$key] = ['count' => 0, 'start' => $now];
        }

        $_SESSION[$key]['count']++;

        return $_SESSION[$key]['count'] <= $max;
    }
}

// ─── 7. AUTH GUARDS ───────────────────────────────────────────────────────────
if (!function_exists('requireLogin')) {
    function requireLogin(): void {
        if (empty($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string ...$roles): void {
        requireLogin();
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            header("Location: login.php");
            exit;
        }
    }
}

// ─── 8. QUERY HELPER (prepared statement wrapper) ────────────────────────────
if (!function_exists('dbQuery')) {
    function dbQuery(string $sql, string $types = '', array $params = []): mysqli_stmt {
        global $conn;
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }
}

// ─── 9. FLASH MESSAGE HELPERS ────────────────────────────────────────────────
if (!function_exists('flash')) {
    function flash(string $type, string $msg): void {
        $_SESSION["flash_{$type}"] = $msg;
    }
}

if (!function_exists('renderFlash')) {
    function renderFlash(): string {
        $html = '';
        foreach (['success' => 'check-circle-fill', 'error' => 'exclamation-triangle-fill', 'warning' => 'exclamation-circle-fill', 'info' => 'info-circle-fill'] as $type => $icon) {
            $key = "flash_{$type}";
            if (!empty($_SESSION[$key])) {
                $colorMap = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
                $cls = $colorMap[$type] ?? 'secondary';
                $html .= '<div class="alert alert-' . $cls . ' alert-dismissible d-flex align-items-center gap-2 shadow-sm border-0 rounded-3 animate__animated animate__fadeInDown" role="alert">'
                       . '<i class="bi bi-' . $icon . '"></i>'
                       . '<div>' . e($_SESSION[$key]) . '</div>'
                       . '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>'
                       . '</div>';
                unset($_SESSION[$key]);
            }
        }
        return $html;
    }
}