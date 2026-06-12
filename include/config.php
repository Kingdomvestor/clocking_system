<?php
// ============================================
// SOTERIA BUSINESS SCHOOL — ATTENDANCE CLOCKING SYSTEM
// ============================================

// --- Database ---
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '5432');
define('DB_USER',    getenv('DB_USER')    ?: 'postgres');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_NAME',    getenv('DB_NAME')    ?: 'postgres');
define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'require');

// --- App Settings ---
define('QR_EXPIRY_SECONDS', 120);
define('SITE_URL',  getenv('SITE_URL')  ?: 'http://localhost/clocking_system');
define('SITE_NAME', 'Soteria Business School Attendance Clocking System');

// Nigeria timezone
date_default_timezone_set('Africa/Lagos');

// ── Database singleton ──────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=' . DB_SSLMODE;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET TIME ZONE 'Africa/Lagos'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ── DB-backed session handler (survives Render restarts) ────────────────────
class DbSessionHandler implements SessionHandlerInterface {
    private PDO $pdo;

    public function open($path, $name): bool {
        $this->pdo = db();
        return true;
    }

    public function close(): bool { return true; }

    public function read($id): string {
        try {
            $stmt = $this->pdo->prepare('SELECT session_data FROM php_sessions WHERE session_id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? $row['session_data'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function write($id, $data): bool {
        try {
            $this->pdo->prepare("
                INSERT INTO php_sessions (session_id, session_data, updated_at)
                VALUES (?, ?, NOW())
                ON CONFLICT (session_id) DO UPDATE
                SET session_data = EXCLUDED.session_data,
                    updated_at   = NOW()
            ")->execute([$id, $data]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function destroy($id): bool {
        try {
            $this->pdo->prepare('DELETE FROM php_sessions WHERE session_id = ?')->execute([$id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function gc($maxlifetime): int {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM php_sessions WHERE updated_at < NOW() - INTERVAL '$maxlifetime seconds'"
            );
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}

// Register DB session handler BEFORE session_start
session_set_save_handler(new DbSessionHandler(), true);

// ── Session ─────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth helpers ────────────────────────────────────────────────────────────
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
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
