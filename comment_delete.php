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
$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;

if($commentId <= 0){
    http_response_code(422);
    echo json_encode(['error' => 'Invalid comment selected.']);
    exit;
}

$commentStmt = db_query(
    $conn,
    "SELECT COMMENT_ID, USER_ID FROM LISTING_COMMENTS WHERE COMMENT_ID=?",
    [$commentId]
);
$comment = $commentStmt ? db_fetch_assoc($commentStmt) : null;

if(!$comment){
    http_response_code(404);
    echo json_encode(['error' => 'Comment not found.']);
    exit;
}

if((int)$comment['USER_ID'] !== $loginId){
    http_response_code(403);
    echo json_encode(['error' => 'You can only delete your own comments.']);
    exit;
}

db_query($conn, "DELETE FROM LISTING_REPORTS WHERE COMMENT_ID=?", [$commentId]);
$deleteStmt = db_query($conn, "DELETE FROM LISTING_COMMENTS WHERE COMMENT_ID=? AND USER_ID=?", [$commentId, $loginId]);

if($deleteStmt === false){
    http_response_code(500);
    echo json_encode(['error' => db_last_error() ?: 'Could not delete the comment.']);
    exit;
}

echo json_encode(['success' => true]);

db_close($conn);
