<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_util.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? $_SESSION['verify_email'] ?? '';
$type  = $_POST['type'] ?? $_SESSION['verify_type'] ?? '';

if (empty($email) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Missing email or type']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Generate new code
$code = generateVerificationCode($conn, $email, $type);

// Fetch user name for the email
$sql = "SELECT FIRST_NAME, LAST_NAME FROM USERS WHERE EMAIL = ? LIMIT 1";
$result = db_query($conn, $sql, [$email]);
$user = db_fetch_assoc($result);
$name = $user ? ($user['FIRST_NAME'] . ' ' . $user['LAST_NAME']) : 'User';

$subject = ($type === 'signup') ? "Verify Your Pipeline Account" : "Your Pipeline Login Code";
$textPart = "Your verification code is: $code";
$htmlPart = "
    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
        <h2 style='color: #087832;'>" . (($type === 'signup') ? "Welcome to Pipeline!" : "Pipeline Login Verification") . "</h2>
        <p>Your new verification code is:</p>
        <div style='background: #f0faf4; padding: 15px; font-size: 24px; font-weight: bold; color: #087832; text-align: center; border-radius: 8px;'>
            $code
        </div>
        <p>This code will expire in 15 minutes.</p>
    </div>
";

$res = sendEmail($email, $name, $subject, $textPart, $htmlPart);

db_close($conn);

echo json_encode($res);
?>
