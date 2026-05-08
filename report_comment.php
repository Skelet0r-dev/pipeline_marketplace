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

function ensureCommentReportColumns($conn): bool {
    $columns = [
        'REPORT_TYPE' => "ALTER TABLE LISTING_REPORTS ADD COLUMN REPORT_TYPE VARCHAR(30) NOT NULL DEFAULT 'listing' AFTER REPORT_ID",
        'COMMENT_ID' => "ALTER TABLE LISTING_REPORTS ADD COLUMN COMMENT_ID INT NULL AFTER LISTING_ID",
    ];

    foreach($columns as $columnName => $alterSql){
        $stmt = db_query(
            $conn,
            "SELECT 1 AS COL_EXISTS
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'LISTING_REPORTS'
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$columnName]
        );
        $row = $stmt ? db_fetch_assoc($stmt) : null;
        if(empty($row['COL_EXISTS']) && db_query($conn, $alterSql) === false){
            return false;
        }
    }

    return true;
}

$loginId = (int)$_SESSION['user_id'];
$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$reportReason = isset($_POST['report_reason']) ? trim($_POST['report_reason']) : '';
$reportDetails = isset($_POST['report_details']) ? trim($_POST['report_details']) : '';

if($commentId <= 0){
    http_response_code(422);
    echo json_encode(['error' => 'Invalid comment selected.']);
    exit;
}

if($reportReason === '' || $reportDetails === ''){
    http_response_code(422);
    echo json_encode(['error' => 'Please provide both a reason and details.']);
    exit;
}

if(!ensureCommentReportColumns($conn)){
    http_response_code(500);
    echo json_encode(['error' => 'Could not prepare comment reports.']);
    exit;
}

$commentStmt = db_query(
    $conn,
    "SELECT C.COMMENT_ID, C.LISTING_ID, C.USER_ID, C.COMMENT_TEXT,
            L.USER_ID AS LISTING_OWNER_ID
     FROM LISTING_COMMENTS C
     JOIN LISTINGS L ON C.LISTING_ID = L.LISTING_ID
     WHERE C.COMMENT_ID=?",
    [$commentId]
);
$comment = $commentStmt ? db_fetch_assoc($commentStmt) : null;

if(!$comment){
    http_response_code(404);
    echo json_encode(['error' => 'Comment not found.']);
    exit;
}

$commentOwnerId = (int)$comment['USER_ID'];
if($commentOwnerId === $loginId){
    http_response_code(403);
    echo json_encode(['error' => 'You cannot report your own comment.']);
    exit;
}

$existingStmt = db_query(
    $conn,
    "SELECT REPORT_ID
     FROM LISTING_REPORTS
     WHERE COMMENT_ID=? AND REPORTER_USER_ID=? AND REPORT_TYPE='comment' AND REPORT_STATUS='Pending'
     ORDER BY CREATED_AT DESC
     LIMIT 1",
    [$commentId, $loginId]
);

if($existingStmt && db_fetch_assoc($existingStmt)){
    echo json_encode(['error' => 'You already sent a pending report for this comment.']);
    exit;
}

$detailsWithContext = $reportDetails . "\n\nReported comment:\n" . mb_substr((string)$comment['COMMENT_TEXT'], 0, 500);
$insertStmt = db_query(
    $conn,
    "INSERT INTO LISTING_REPORTS
        (REPORT_TYPE, LISTING_ID, COMMENT_ID, REPORTER_USER_ID, LISTING_OWNER_USER_ID, REPORT_REASON, REPORT_DETAILS, REPORT_STATUS)
     VALUES ('comment', ?, ?, ?, ?, ?, ?, 'Pending')",
    [
        (int)$comment['LISTING_ID'],
        $commentId,
        $loginId,
        $commentOwnerId,
        $reportReason,
        $detailsWithContext,
    ]
);

if($insertStmt === false){
    http_response_code(500);
    echo json_encode(['error' => db_last_error() ?: 'Could not save the comment report.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Comment report submitted for admin review.'
]);

db_close($conn);
