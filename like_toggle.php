<?php
// ============================================================
// like_toggle.php  –  Pipeline Listing Like Toggle
// Returns JSON  →  easy to swap for a REST API endpoint later
// POST: listing_id  (user_id from session)
// ============================================================
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$serverName=".\SQLEXPRESS";
$connectionOptions=["Database"=>"pipeline_db","Uid"=>"","PWD"=>""];
$conn=sqlsrv_connect($serverName,$connectionOptions);

if(!$conn){
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if(!$listingId){
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing_id']);
    exit;
}

// Check if like already exists
$sqlCheck = "SELECT LIKE_ID FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=? AND USER_ID=?";
$resCheck  = sqlsrv_query($conn, $sqlCheck, [$listingId, $userId]);
$existing  = sqlsrv_fetch_array($resCheck, SQLSRV_FETCH_ASSOC);

if($existing){
    // Unlike
    sqlsrv_query($conn,
        "DELETE FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=? AND USER_ID=?",
        [$listingId, $userId]
    );
    $liked = false;
} else {
    // Like
    sqlsrv_query($conn,
        "INSERT INTO dbo.[LISTING_LIKES] (LISTING_ID, USER_ID) VALUES (?,?)",
        [$listingId, $userId]
    );
    $liked = true;
}

// Return updated count
$resCount = sqlsrv_query($conn,
    "SELECT COUNT(*) AS CNT FROM dbo.[LISTING_LIKES] WHERE LISTING_ID=?",
    [$listingId]
);
$rowCount = sqlsrv_fetch_array($resCount, SQLSRV_FETCH_ASSOC);

echo json_encode([
    'liked' => $liked,
    'count' => (int)$rowCount['CNT']
]);

sqlsrv_close($conn);