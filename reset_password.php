<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_util.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$email    = strtolower(trim($_POST['email'] ?? $_SESSION['reset_email'] ?? ''));
$code     = trim($_POST['code'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if (empty($email) || empty($code) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Verify the OTP
if (!verifyCode($conn, $email, $code, 'password_reset')) {
    db_close($conn);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired reset code. Please request a new one.']);
    exit;
}

// Update password
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt   = db_query($conn, "UPDATE USERS SET PASSWORD = ? WHERE LOWER(TRIM(EMAIL)) = ?", [$hashed, $email]);

db_close($conn);

if ($stmt) {
    // Clear reset session
    unset($_SESSION['reset_email']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update password. Please try again.']);
}
?>
