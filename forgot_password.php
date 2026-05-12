<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_util.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

// Basic validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please try again later.']);
    exit;
}

// Check if the email exists in the USERS table
$res = db_query($conn, "SELECT USER_ID, FIRST_NAME, LAST_NAME FROM USERS WHERE LOWER(TRIM(EMAIL)) = ? LIMIT 1", [$email]);
$user = db_fetch_assoc($res);

if (!$user) {
    db_close($conn);
    echo json_encode(['success' => false, 'error' => 'This email is not registered in our system.']);
    exit;
}

// Email exists — generate and send OTP
$code    = generateVerificationCode($conn, $email, 'password_reset');
$name    = trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME']);
$subject = "Pipeline — Password Reset Code";

$textPart = "Your password reset code is: $code\nThis code will expire in 15 minutes.";
$htmlPart = "
    <div style='font-family: Arial, sans-serif; padding: 24px; color: #333; max-width: 480px; margin: auto;'>
        <h2 style='color: #087832; margin-bottom: 4px;'>Password Reset</h2>
        <p style='color: #555; margin-bottom: 20px;'>Hi $name, we received a request to reset your Pipeline password.</p>
        <p style='margin-bottom: 8px;'>Use the code below to reset your password:</p>
        <div style='background: #f0faf4; padding: 20px; font-size: 32px; font-weight: 900; color: #087832;
                    text-align: center; border-radius: 12px; letter-spacing: 10px; margin-bottom: 20px;'>
            $code
        </div>
        <p style='font-size: 13px; color: #888;'>This code will expire in <strong>15 minutes</strong>. If you didn't request a password reset, you can safely ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #eee; margin: 24px 0;'>
        <p style='font-size: 12px; color: #aaa; text-align: center;'>Pipeline Marketplace &mdash; DLSU-D Campus Exclusive</p>
    </div>
";

$result = sendEmail($email, $name, $subject, $textPart, $htmlPart);

db_close($conn);

if ($result['success']) {
    // Store in session so OTP verify step can confirm it
    $_SESSION['reset_email'] = $email;
    echo json_encode(['success' => true]);
} else {
    $errDetail = $result['error'] ?? ('HTTP ' . ($result['code'] ?? '?'));
    echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again. (' . $errDetail . ')']);
}
?>
