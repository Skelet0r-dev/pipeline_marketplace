<?php
// ============================================================
// like_toggle.php  –  Pipeline Listing Like Toggle
// Returns JSON  →  easy to swap for a REST API endpoint later
// POST: listing_id  (user_id from session)
// ============================================================
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = db_connect();
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
$sqlCheck = "SELECT LIKE_ID FROM LISTING_LIKES WHERE LISTING_ID=? AND USER_ID=?";
$resCheck  = db_query($conn, $sqlCheck, [$listingId, $userId]);
$existing  = db_fetch_assoc($resCheck);

if($existing){
    // Unlike
    db_query($conn,
        "DELETE FROM LISTING_LIKES WHERE LISTING_ID=? AND USER_ID=?",
        [$listingId, $userId]
    );
    $liked = false;
} else {
    // Like
    db_query($conn,
        "INSERT INTO LISTING_LIKES (LISTING_ID, USER_ID) VALUES (?,?)",
        [$listingId, $userId]
    );
    $liked = true;
}

// Return updated count
$resCount = db_query($conn,
    "SELECT COUNT(*) AS CNT FROM LISTING_LIKES WHERE LISTING_ID=?",
    [$listingId]
);
$rowCount = db_fetch_assoc($resCount);

echo json_encode([
    'liked' => $liked,
    'count' => (int)$rowCount['CNT']
]);

db_close($conn);
