<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? $_SESSION['verify_email'] ?? ''));
$code  = trim($_POST['code'] ?? '');
$type  = trim($_POST['type'] ?? $_SESSION['verify_type'] ?? '');

if (empty($email) || empty($type)) {
    header('Location: login.html?error=session_lost');
    exit;
}

$conn = db_connect();
if (!$conn) die('Database connection failed.');

if (verifyCode($conn, $email, $code, $type)) {
    // Fetch user info for auto-login
    $sql_user = "SELECT USER_ID FROM USERS WHERE LOWER(EMAIL) = LOWER(?) LIMIT 1";
    $res_user = db_query($conn, $sql_user, [$email]);
    $user = db_fetch_assoc($res_user);

    if ($type === 'signup') {
        // Mark user as verified
        $sql = "UPDATE USERS SET VERIFIED = 1 WHERE EMAIL = ?";
        db_query($conn, $sql, [$email]);
        
        if ($user) {
            $_SESSION['user_id'] = $user['USER_ID'];
            // Log auto-login after signup verification
            log_audit($conn, $user['USER_ID'], $email, 'SUCCESS');

            header('Location: registration_success.php');
        } else {
            header('Location: registration_success.php');
        }
    } else {
        // Login verification (2FA)
        
        if ($user) {
            $_SESSION['user_id'] = $user['USER_ID'];

            // Log successful login
            log_audit($conn, $user['USER_ID'], $email, 'SUCCESS');

            header('Location: dashboard.php');
        } else {
            header('Location: login.html?error=user_not_found');
        }
    }
} else {
    // Invalid code
    header("Location: verify.php?email=" . urlencode($email) . "&type=" . $type . "&error=invalid");
}

db_close($conn);
?>
