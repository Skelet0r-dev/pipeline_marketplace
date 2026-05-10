<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_util.php';

$conn = db_connect();
if($conn == false) die(db_last_error());

// ── Collect POST data ────────────────────────────────────────────────────────
$firstname  = strtoupper(trim($_POST['f_name'] ?? ''));
$lastname   = strtoupper(trim($_POST['l_name'] ?? ''));
$stdnum     = trim($_POST['std_num'] ?? '');
$college    = trim($_POST['college'] ?? '');
$department = trim($_POST['department'] ?? '');
$section    = trim($_POST['section'] ?? '');
$sex        = trim($_POST['sex'] ?? '');
$username   = trim($_POST['username'] ?? '');
$email      = strtolower(trim($_POST['email'] ?? ''));
$password   = $_POST['password'] ?? '';

// ── Validation ────────────────────────────────────────────────────────────────
$errors = [];
if(empty($firstname))  $errors[] = "First name is required.";
if(empty($lastname))   $errors[] = "Last name is required.";
if(empty($stdnum))     $errors[] = "Student number is required.";
if(empty($username))   $errors[] = "Username is required.";
if(empty($email))      $errors[] = "Email is required.";
if(empty($password))   $errors[] = "Password is required.";

if(!preg_match('/^\d{9}$/', $stdnum))
    $errors[] = "Student number must be exactly 9 digits.";

if(!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@dlsud.edu.ph'))
    $errors[] = "Please use your valid @dlsud.edu.ph email.";

// ── Check Duplicates ──────────────────────────────────────────────────────────
$res = db_query($conn, "SELECT USER_ID FROM USERS WHERE STD_NUM = ? OR EMAIL = ?", [$stdnum, $email]);
if(db_fetch($res)) {
    $errors[] = "Student number or Email already registered.";
}

if(!empty($errors)) {
    // If AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    // For standard form submission (fallback)
    $_SESSION['reg_errors'] = $errors;
    header('Location: login.html#signup');
    exit;
}

// ── Image Upload ─────────────────────────────────────────────────────────────
$target_file = "";
if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0) {
    $target_dir = "uploads/profiles/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
    $target_file = $target_dir . bin2hex(random_bytes(8)) . "." . $file_ext;
    move_uploaded_file($_FILES['profile_img']['tmp_name'], $target_file);
}

// ── Hash Password ────────────────────────────────────────────────────────────
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// ── Insert User ──────────────────────────────────────────────────────────────
$date_now = date('Y-m-d');
$sql = "INSERT INTO USERS (FIRST_NAME, LAST_NAME, STD_NUM, COLLEGE, DEPARTMENT, SECTION, SEX, USERNAME, EMAIL, PASSWORD, VERIFIED, DATE_REGISTERED) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
$params = [$firstname, $lastname, $stdnum, $college, $department, $section, $sex, $username, $email, $hashed_password, $date_now];

$stmt = db_query($conn, $sql, $params);
if (!$stmt) {
    $err = db_last_error();
    file_put_contents(__DIR__ . '/reg_db_error.log', "[" . date('Y-m-d H:i:s') . "] INSERT FAILED: $err\nParams: " . print_r($params, true) . "\n", FILE_APPEND);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Database insertion failed. Please contact admin.']]);
    exit;
}

$userId = db_last_insert_id($conn);
file_put_contents(__DIR__ . '/reg_success.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Created User ID $userId ($email)\n", FILE_APPEND);

// ── Store Image ──
if ($target_file) {
    $res_img = db_query($conn, "INSERT INTO USER_IMG (USER_ID, FILE_PATH) VALUES (?, ?)", [$userId, $target_file]);
    if (!$res_img) {
        file_put_contents(__DIR__ . '/reg_db_error.log', "[" . date('Y-m-d H:i:s') . "] IMG INSERT FAILED for User $userId: " . db_last_error() . "\n", FILE_APPEND);
    }
}

// ── OTP & Email ──────────────────────────────────────────────────────────────
$code = generateVerificationCode($conn, $email, 'signup');
$subject = "Verify Your Pipeline Account";
$textPart = "Your verification code is: $code";
$htmlPart = "
    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
        <h2 style='color: #087832;'>Welcome to Pipeline!</h2>
        <p>Thank you for registering. Please use the code below to verify your account:</p>
        <div style='background: #f0faf4; padding: 15px; font-size: 24px; font-weight: bold; color: #087832; text-align: center; border-radius: 8px;'>
            $code
        </div>
        <p>This code will expire in 15 minutes.</p>
    </div>
";

sendEmail($email, "$firstname $lastname", $subject, $textPart, $htmlPart);

// ── Cleanup and Redirect ─────────────────────────────────────────────────────
db_close($conn);

$_SESSION['verify_email'] = $email;
$_SESSION['verify_type']  = 'signup';

// If AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => "verify.php?email=" . urlencode($email) . "&type=signup"]);
    exit;
}

header("Location: verify.php?email=" . urlencode($email) . "&type=signup");
exit;
?>
