<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'DB failed']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Fetch unread notifications
$sql = "SELECT N.*, U.FIRST_NAME, U.LAST_NAME, UI.FILE_PATH as SENDER_AVATAR 
        FROM NOTIFICATIONS N
        JOIN USERS U ON N.SENDER_ID = U.USER_ID
        LEFT JOIN USER_IMG UI ON N.SENDER_ID = UI.USER_ID
        WHERE N.USER_ID = ? AND N.IS_READ = 0
        ORDER BY N.CREATED_AT DESC";
$res = db_query($conn, $sql, [$userId]);

$notifications = [];
while ($row = db_fetch_assoc($res)) {
    $notifications[] = [
        'id' => (int)$row['NOTIFICATION_ID'],
        'sender' => $row['FIRST_NAME'] . ' ' . $row['LAST_NAME'],
        'avatar' => $row['SENDER_AVATAR'] ?: 'assets/img/avatar.png',
        'message' => $row['MESSAGE'],
        'type' => $row['TYPE'],
        'listing_id' => (int)$row['LISTING_ID'],
        'time' => date('g:i A', strtotime($row['CREATED_AT']))
    ];
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
db_close($conn);
?>
