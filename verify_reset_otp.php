<?php
/**
 * verify_reset_otp.php
 * 
 * Lightweight OTP check for password reset step 2.
 * Only validates the code without consuming it — the code is consumed on final reset_password.php call.
 * 
 * NOTE: We peek at the record without deleting so the same code can be submitted in step 3.
 */
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$code  = trim($_POST['code'] ?? '');

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Missing fields.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Peek — do NOT delete the row; reset_password.php will consume it
$sql = "SELECT VERIFY_ID FROM USER_VERIFICATION 
        WHERE LOWER(TRIM(EMAIL)) = LOWER(TRIM(?)) 
          AND TRIM(CODE) = TRIM(?) 
          AND TYPE = 'password_reset' 
          AND EXPIRES_AT > ? 
        LIMIT 1";

$res = db_query($conn, $sql, [$email, $code, date('Y-m-d H:i:s')]);
$row = db_fetch_assoc($res);

db_close($conn);

if ($row) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired code. Please request a new one.']);
}
?>
