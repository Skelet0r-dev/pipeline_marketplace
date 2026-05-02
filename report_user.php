<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$conn = db_connect();
if($conn === false){
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$loginId = (int)$_SESSION['user_id'];
$reportedUserId = isset($_POST['reported_user_id']) ? (int)$_POST['reported_user_id'] : 0;
$reason = isset($_POST['report_reason']) ? trim($_POST['report_reason']) : '';
$details = isset($_POST['report_details']) ? trim($_POST['report_details']) : '';

function ensureUserReportsTable($conn){
    $existsStmt = db_query(
        $conn,
        "SELECT 1 AS TABLE_ID
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'USER_REPORTS'
         LIMIT 1"
    );
    $existsRow = $existsStmt ? db_fetch_assoc($existsStmt) : null;

    if(!empty($existsRow['TABLE_ID'])){
        return true;
    }

    $createSql = "CREATE TABLE USER_REPORTS (
        REPORT_ID INT AUTO_INCREMENT PRIMARY KEY,
        REPORTED_USER_ID INT NOT NULL,
        REPORTER_USER_ID INT NOT NULL,
        REPORT_REASON VARCHAR(100) NOT NULL,
        REPORT_DETAILS VARCHAR(1000) NOT NULL,
        PROOF_FILE_PATH VARCHAR(255) NULL,
        REPORT_STATUS VARCHAR(30) NOT NULL DEFAULT 'Pending',
        CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";

    return (bool)db_query($conn, $createSql);
}

if($reportedUserId <= 0){
    http_response_code(422);
    echo json_encode(['error' => 'Invalid profile selected.']);
    exit;
}

if($reportedUserId === $loginId){
    http_response_code(403);
    echo json_encode(['error' => 'You cannot report your own profile.']);
    exit;
}

if($reason === '' || $details === ''){
    http_response_code(422);
    echo json_encode(['error' => 'Please provide a reason and justification.']);
    exit;
}

$userStmt = db_query($conn, "SELECT USER_ID FROM USERS WHERE USER_ID=?", [$reportedUserId]);
if(!$userStmt || !db_fetch_assoc($userStmt)){
    http_response_code(404);
    echo json_encode(['error' => 'User profile not found.']);
    exit;
}

if(!ensureUserReportsTable($conn)){
    http_response_code(500);
    echo json_encode(['error' => 'Could not prepare the user reports table.']);
    exit;
}

$proofPath = null;
if(isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] !== UPLOAD_ERR_NO_FILE){
    if($_FILES['proof_photo']['error'] !== UPLOAD_ERR_OK){
        http_response_code(422);
        echo json_encode(['error' => 'Could not upload the proof photo.']);
        exit;
    }

    if($_FILES['proof_photo']['size'] > 5 * 1024 * 1024){
        http_response_code(422);
        echo json_encode(['error' => 'Proof photo must be 5MB or smaller.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if(!in_array($ext, $allowed, true)){
        http_response_code(422);
        echo json_encode(['error' => 'Proof photo must be JPG, PNG, or WEBP.']);
        exit;
    }

    $uploadDir = 'uploads/report_proofs';
    if(!is_dir($uploadDir)){
        mkdir($uploadDir, 0777, true);
    }

    $proofPath = $uploadDir . '/user_' . $reportedUserId . '_report_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if(!move_uploaded_file($_FILES['proof_photo']['tmp_name'], $proofPath)){
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the proof photo.']);
        exit;
    }
}

$existingStmt = db_query(
    $conn,
    "SELECT REPORT_ID FROM USER_REPORTS
     WHERE REPORTED_USER_ID=? AND REPORTER_USER_ID=? AND REPORT_STATUS='Pending'
     ORDER BY CREATED_AT DESC
     LIMIT 1",
    [$reportedUserId, $loginId]
);

if($existingStmt && db_fetch_assoc($existingStmt)){
    echo json_encode(['error' => 'You already sent a pending report for this profile.']);
    exit;
}

$insertStmt = db_query(
    $conn,
    "INSERT INTO USER_REPORTS
        (REPORTED_USER_ID, REPORTER_USER_ID, REPORT_REASON, REPORT_DETAILS, PROOF_FILE_PATH, REPORT_STATUS)
     VALUES (?, ?, ?, ?, ?, 'Pending')",
    [$reportedUserId, $loginId, $reason, $details, $proofPath]
);

if($insertStmt === false){
    http_response_code(500);
    echo json_encode(['error' => db_last_error() ?: 'Could not save the profile report.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Profile report submitted for admin review.'
]);

db_close($conn);
?>
