<?php
// ============================================
// SOTERIA BUSINESS SCHOOL — ATTENDANCE CLOCKING SYSTEM
// ============================================


// --- Database ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // ← Change after deployment
define('DB_NAME', 'clocking_system');

// --- App Settings ---
define('QR_EXPIRY_SECONDS', 120);
// FIX: SITE_URL must be the app ROOT (no trailing slash, no filename).
// All internal links are built as SITE_URL . '/admin/dashboard.php' etc.
define('SITE_URL',  'http://localhost/clocking_system'); // ← Change to your domain
define('SITE_NAME', 'Soteria Business School Attendance Clocking System');

// Nigeria timezone (WAT = UTC+1)
date_default_timezone_set('Africa/Lagos');

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database singleton ──────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+01:00'"); // Sync MySQL to WAT
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ── Auth helpers ────────────────────────────────────────────────────────────
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        // FIX: Was SITE_URL . '/admin/login.php' which would have appended
        // '/admin/login.php' to a URL that already contained a filename.
        // Now SITE_URL is the root so this resolves correctly.
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

// ── JSON response helper ────────────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}