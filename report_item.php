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
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$reportReason = isset($_POST['report_reason']) ? trim($_POST['report_reason']) : '';
$reportDetails = isset($_POST['report_details']) ? trim($_POST['report_details']) : '';

if($listingId <= 0){
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing selected.']);
    exit;
}

if($reportReason === '' || $reportDetails === ''){
    http_response_code(422);
    echo json_encode(['error' => 'Please provide both a reason and details.']);
    exit;
}

$listingStmt = db_query(
    $conn,
    "SELECT LISTING_ID, USER_ID, TITLE FROM LISTINGS WHERE LISTING_ID=?",
    [$listingId]
);
$listing = db_fetch_assoc($listingStmt);

if(!$listing){
    http_response_code(404);
    echo json_encode(['error' => 'Listing not found.']);
    exit;
}

$ownerId = (int)$listing['USER_ID'];
if($ownerId === $loginId){
    http_response_code(403);
    echo json_encode(['error' => 'You cannot report your own listing.']);
    exit;
}

$existingStmt = db_query(
    $conn,
    "SELECT REPORT_ID
     FROM LISTING_REPORTS
     WHERE LISTING_ID=? AND REPORTER_USER_ID=? AND REPORT_STATUS='Pending'
     ORDER BY CREATED_AT DESC
     LIMIT 1",
    [$listingId, $loginId]
);

if($existingStmt && db_fetch_assoc($existingStmt)){
    echo json_encode(['error' => 'You already sent a pending report for this item.']);
    exit;
}

$insertStmt = db_query(
    $conn,
    "INSERT INTO LISTING_REPORTS
        (LISTING_ID, REPORTER_USER_ID, LISTING_OWNER_USER_ID, REPORT_REASON, REPORT_DETAILS, REPORT_STATUS)
     VALUES (?, ?, ?, ?, ?, 'Pending')",
    [$listingId, $loginId, $ownerId, $reportReason, $reportDetails]
);

if($insertStmt === false){
    http_response_code(500);
    echo json_encode(['error' => db_last_error() ?: 'Could not save the report.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Report submitted! Waiting for admin review.'
]);

db_close($conn);
?>
