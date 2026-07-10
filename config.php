<?php
/**
 * config.php
 * Central configuration: DB connection, session hardening, IP helpers.
 * Include this at the top of every entry-point script.
 */

// ---------- Error handling (turn display_errors OFF in production) ----------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to 1 only while developing locally
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
date_default_timezone_set('Asia/Kolkata');

// ---------- Hardened session cookie settings ----------
// Must be set BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',       // set your real domain in production
    'secure'   => false,    // set TRUE once you're serving over HTTPS
    'httponly' => true,     // JS can never read the session/device cookie
    'samesite' => 'Strict',
]);
session_start();

// ---------- Database credentials (WAMP defaults shown) ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance');
define('DB_USER', 'root');
define('DB_PASS', '');        // set a real password on your MySQL root user

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        ]);
    }
    return $pdo;
}

// ---------- Client IP (be aware: trusting X-Forwarded-For is only safe
// if you control the proxy in front of Apache; on a plain WAMP LAN
// setup REMOTE_ADDR is what you want) ----------
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check whether $ip falls inside $cidr, e.g. ip_in_cidr('192.168.1.55','192.168.1.0/24')
 */
function ip_in_cidr(string $ip, string $cidr): bool {
    if ($cidr === '0.0.0.0/0') {
        return true; // Bypass check for testing
    }
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr; // exact match fallback
    }
    [$subnet, $bits] = explode('/', $cidr);
    $bits = (int)$bits;

    $ipBin     = inet_pton($ip);
    $subnetBin = inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $bytes = intdiv($bits, 8);
    $remBits = $bits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    if ($remBits > 0) {
        $maskByte = ~(0xFF >> $remBits) & 0xFF;
        $ipByte     = ord($ipBin[$bytes]);
        $subnetByte = ord($subnetBin[$bytes]);
        if (($ipByte & $maskByte) !== ($subnetByte & $maskByte)) {
            return false;
        }
    }
    return true;
}

// ---------- CSRF helpers ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- Simple rate limiter (per IP, per roll_no) ----------
function too_many_attempts(PDO $pdo, string $ip, string $rollNo, int $maxAttempts = 5, int $windowMinutes = 10): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND roll_no = ? AND success = 0
           AND attempted_at > (NOW() - INTERVAL ? MINUTE)"
    );
    $stmt->execute([$ip, $rollNo, $windowMinutes]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

function log_attempt(PDO $pdo, string $ip, string $rollNo, bool $success): void {
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (ip_address, roll_no, success) VALUES (?, ?, ?)"
    );
    $stmt->execute([$ip, $rollNo, $success ? 1 : 0]);
}

// ---------- Teacher Auth Helpers ----------
function require_teacher(): array {
    if (empty($_SESSION['teacher_id'])) {
        header('Location: login.php');
        exit;
    }
    
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$_SESSION['teacher_id']]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        unset($_SESSION['teacher_id']);
        header('Location: login.php');
        exit;
    }
    
    return $teacher;
}
