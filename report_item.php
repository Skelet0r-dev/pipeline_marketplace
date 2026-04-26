<?php
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$serverName=".\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db","Uid"=>"","PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

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

$listingStmt = sqlsrv_query(
    $conn,
    "SELECT LISTING_ID, USER_ID, TITLE FROM dbo.[LISTINGS] WHERE LISTING_ID=?",
    [$listingId]
);
$listing = sqlsrv_fetch_array($listingStmt, SQLSRV_FETCH_ASSOC);

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

$existingStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 REPORT_ID
     FROM dbo.[LISTING_REPORTS]
     WHERE LISTING_ID=? AND REPORTER_USER_ID=? AND REPORT_STATUS='Pending'
     ORDER BY CREATED_AT DESC",
    [$listingId, $loginId]
);

if($existingStmt && sqlsrv_fetch_array($existingStmt, SQLSRV_FETCH_ASSOC)){
    echo json_encode(['error' => 'You already sent a pending report for this item.']);
    exit;
}

$insertStmt = sqlsrv_query(
    $conn,
    "INSERT INTO dbo.[LISTING_REPORTS]
        (LISTING_ID, REPORTER_USER_ID, LISTING_OWNER_USER_ID, REPORT_REASON, REPORT_DETAILS, REPORT_STATUS)
     VALUES (?, ?, ?, ?, ?, 'Pending')",
    [$listingId, $loginId, $ownerId, $reportReason, $reportDetails]
);

if($insertStmt === false){
    $errors = sqlsrv_errors();
    http_response_code(500);
    echo json_encode(['error' => $errors[0]['message'] ?? 'Could not save the report.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Report submitted! Waiting for admin review.'
]);

sqlsrv_close($conn);
?>
