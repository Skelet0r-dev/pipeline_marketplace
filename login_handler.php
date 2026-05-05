<?php
session_start();
require_once __DIR__ . '/db.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

// ── Rate limiting config ──────────────────────────────────────────────────────
define('MAX_ATTEMPTS',  5);   // failed attempts before lockout
define('COOLDOWN_SECS', 60);  // seconds to wait after lockout

// ── Initialize attempt tracking in session ────────────────────────────────────
if (!isset($_SESSION['login_attempts']))  $_SESSION['login_attempts']  = 0;
if (!isset($_SESSION['login_locked_at'])) $_SESSION['login_locked_at'] = null;

// ── Check if currently in cooldown ───────────────────────────────────────────
if ($_SESSION['login_locked_at'] !== null) {
    $elapsed   = time() - $_SESSION['login_locked_at'];
    $remaining = COOLDOWN_SECS - $elapsed;

    if ($remaining > 0) {
        // Still locked — pass remaining seconds for the countdown modal
        header('Location: login.html?error=cooldown&remaining=' . $remaining);
        exit;
    } else {
        // Cooldown over — reset
        $_SESSION['login_attempts']  = 0;
        $_SESSION['login_locked_at'] = null;
    }
}

// ── Read inputs ───────────────────────────────────────────────────────────────
$stdnum   = trim($_POST['std_num']   ?? '');
$password = $_POST['password'] ?? '';

// ── Basic input checks ────────────────────────────────────────────────────────
if (empty($stdnum) || !preg_match('/^\d{9}$/', $stdnum) || empty($password)) {
    header('Location: login.html?error=invalid_input');
    exit;
}

// ── Query DB ──────────────────────────────────────────────────────────────────
$conn = db_connect();
if (!$conn) die('Database connection failed.');

$stmt = db_query($conn, "SELECT * FROM USERS WHERE STD_NUM = ?", [$stdnum]);
$user = db_fetch_assoc($stmt);
db_close($conn);

// ── Validate credentials ──────────────────────────────────────────────────────
if (!$user || !password_verify($password, $user['PASSWORD'])) {
    $_SESSION['login_attempts']++;
    $attemptsLeft = MAX_ATTEMPTS - $_SESSION['login_attempts'];

    if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
        // Trigger lockout
        $_SESSION['login_locked_at'] = time();
        header('Location: login.html?error=cooldown&remaining=' . COOLDOWN_SECS);
        exit;
    }

    // Still have attempts left — warn the user
    header('Location: login.html?error=invalid_credentials&attempts_left=' . $attemptsLeft);
    exit;
}

// ── Success — clear attempt tracking and set session ─────────────────────────
$_SESSION['login_attempts']  = 0;
$_SESSION['login_locked_at'] = null;
$_SESSION['user_id']         = $user['USER_ID'];
header('Location: dashboard.php');
exit;