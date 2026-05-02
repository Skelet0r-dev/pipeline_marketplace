<?php
// ============================================================
// comment_post.php  –  Pipeline Post Comment
// Returns JSON  →  easy to swap for a REST API endpoint later
// POST: listing_id, comment_text
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

$userId      = (int)$_SESSION['user_id'];
$listingId   = isset($_POST['listing_id'])   ? (int)$_POST['listing_id']          : 0;
$commentText = isset($_POST['comment_text']) ? trim($_POST['comment_text'])        : '';

if(!$listingId || $commentText === ''){
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Truncate for safety
$commentText = mb_substr($commentText, 0, 1000);

$sqlInsert = "INSERT INTO LISTING_COMMENTS (LISTING_ID, USER_ID, COMMENT_TEXT) VALUES (?,?,?)";
$result    = db_query($conn, $sqlInsert, [$listingId, $userId, $commentText]);

if(!$result){
    http_response_code(500);
    echo json_encode(['error' => db_last_error() ?: 'Insert failed']);
    exit;
}

// Fetch the newly inserted comment with user info to return to frontend
$sqlNew = "SELECT C.COMMENT_ID, C.COMMENT_TEXT, C.CREATED_AT,
                  U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
                  UI.FILE_PATH AS AVATAR
           FROM LISTING_COMMENTS C
           JOIN USERS U ON C.USER_ID = U.USER_ID
           LEFT JOIN USER_IMG UI ON C.USER_ID = UI.USER_ID
           WHERE C.LISTING_ID=? AND C.USER_ID=?
           ORDER BY C.COMMENT_ID DESC
           LIMIT 1";
$resNew = db_query($conn, $sqlNew, [$listingId, $userId]);
$row    = db_fetch_assoc($resNew);

$createdAt = $row['CREATED_AT'] instanceof DateTime
    ? $row['CREATED_AT']->format('M d, Y g:i A')
    : date('M d, Y g:i A');

echo json_encode([
    'success'      => true,
    'comment_id'   => (int)$row['COMMENT_ID'],
    'comment_text' => htmlspecialchars($row['COMMENT_TEXT']),
    'first_name'   => htmlspecialchars($row['FIRST_NAME']),
    'last_name'    => htmlspecialchars($row['LAST_NAME']),
    'username'     => htmlspecialchars($row['USERNAME']),
    'avatar'       => htmlspecialchars($row['AVATAR'] ?? 'assets/img/default_avatar.png'),
    'created_at'   => $createdAt
]);

db_close($conn);
