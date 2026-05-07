<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/db.php';
$conn = db_connect();

$loginId = (int)$_SESSION['user_id'];
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if (!$listingId) {
    echo json_encode(['error' => 'Invalid listing ID']);
    exit;
}

// Check if already saved
$sqlCheck = "SELECT SAVE_ID FROM LISTING_SAVED WHERE USER_ID = ? AND LISTING_ID = ?";
$resCheck = db_query($conn, $sqlCheck, [$loginId, $listingId]);
$rowCheck = db_fetch_assoc($resCheck);

if ($rowCheck) {
    // Unsave
    $sqlUnsave = "DELETE FROM LISTING_SAVED WHERE USER_ID = ? AND LISTING_ID = ?";
    db_query($conn, $sqlUnsave, [$loginId, $listingId]);
    $saved = false;
} else {
    // Save
    $sqlSave = "INSERT INTO LISTING_SAVED (USER_ID, LISTING_ID) VALUES (?, ?)";
    db_query($conn, $sqlSave, [$loginId, $listingId]);
    $saved = true;
}

echo json_encode([
    'saved' => $saved
]);

db_close($conn);
