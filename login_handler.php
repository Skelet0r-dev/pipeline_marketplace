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

// ── Check if user is verified ───────────────────────────────────────────────
if ($user['VERIFIED'] == 0) {
    require_once __DIR__ . '/mail_util.php';
    $conn = db_connect();
    $code = generateVerificationCode($conn, $user['EMAIL'], 'signup');
    
    $subject = "Verify Your Pipeline Account";
    $textPart = "Your verification code is: $code";
    $htmlPart = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #087832;'>Complete Your Registration</h2>
            <p>Please verify your account using the code below:</p>
            <div style='background: #f0faf4; padding: 15px; font-size: 24px; font-weight: bold; color: #087832; text-align: center; border-radius: 8px;'>
                $code
            </div>
            <p>This code will expire in 15 minutes.</p>
        </div>
    ";
    sendEmail($user['EMAIL'], $user['FIRST_NAME'], $subject, $textPart, $htmlPart);
    db_close($conn);
    $_SESSION['verify_email'] = $user['EMAIL'];
    $_SESSION['verify_type']  = 'signup';
    header("Location: verify.php?email=" . urlencode($user['EMAIL']) . "&type=signup");
    exit;
}

// ── Successful Password Verification — Now send Login OTP ──────────────────
require_once __DIR__ . '/mail_util.php';
$conn = db_connect(); // Re-open since we closed it above
$code = generateVerificationCode($conn, $user['EMAIL'], 'login');

$subject = "Your Pipeline Login Code";
$textPart = "Your login verification code is: $code";
$htmlPart = "
    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
        <h2 style='color: #087832;'>Pipeline Login Verification</h2>
        <p>Hello " . htmlspecialchars($user['FIRST_NAME']) . ",</p>
        <p>You are trying to log in to your account. Please use the verification code below:</p>
        <div style='background: #f0faf4; padding: 15px; font-size: 24px; font-weight: bold; color: #087832; text-align: center; border-radius: 8px;'>
            $code
        </div>
        <p>This code will expire in 15 minutes.</p>
        <p>If you didn't attempt to log in, please secure your account immediately.</p>
    </div>
";

sendEmail($user['EMAIL'], $user['FIRST_NAME'], $subject, $textPart, $htmlPart);
db_close($conn);

// Redirect to verify page
header("Location: verify.php?email=" . urlencode($user['EMAIL']) . "&type=login");
exit;