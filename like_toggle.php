<?php
// ============================================================
// like_toggle.php  –  Pipeline Listing Like Toggle
// Returns JSON  →  easy to swap for a REST API endpoint later
// POST: listing_id  (user_id from session)
// ============================================================
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/listing_reactions.php';

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
$reactionType = normalize_listing_reaction($_POST['reaction_type'] ?? null);

if(!$listingId){
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing_id']);
    exit;
}

listing_reactions_ensure_schema($conn);

// Selecting the same reaction removes it; selecting a different one swaps it.
$existing = listing_user_reaction($conn, $listingId, $userId);

if($existing && $existing['REACTION_TYPE'] === $reactionType){
    db_query($conn,
        "DELETE FROM LISTING_LIKES WHERE LISTING_ID=? AND USER_ID=?",
        [$listingId, $userId]
    );
    $selectedReaction = null;
} elseif($existing) {
    db_query($conn,
        "UPDATE LISTING_LIKES
         SET REACTION_TYPE=?, CREATED_AT=NOW()
         WHERE LISTING_ID=? AND USER_ID=?",
        [$reactionType, $listingId, $userId]
    );
    $selectedReaction = $reactionType;
} else {
    db_query($conn,
        "INSERT INTO LISTING_LIKES (LISTING_ID, USER_ID, REACTION_TYPE, CREATED_AT) VALUES (?,?,?,NOW())",
        [$listingId, $userId, $reactionType]
    );
    $selectedReaction = $reactionType;
}

// ── Trigger Notification ───────────────────────────────────────────────────
if ($selectedReaction !== null) {
    // Find the owner of the listing
    $sqlOwner = "SELECT USER_ID, TITLE FROM LISTINGS WHERE LISTING_ID = ? LIMIT 1";
    $resOwner = db_query($conn, $sqlOwner, [$listingId]);
    $owner = db_fetch_assoc($resOwner);

    if ($owner && (int)$owner['USER_ID'] !== $userId) {
        // Only notify if the liker is NOT the owner
        $ownerId = (int)$owner['USER_ID'];
        $listingTitle = $owner['TITLE'];
        
        $emoji = '👍'; // Default
        if (strtolower($selectedReaction) === 'heart_eyes') $emoji = '😍';
        else if (strtolower($selectedReaction) === 'thumbs_down') $emoji = '👎';
        
        $msg = "reacted $emoji to your listing: \"$listingTitle\"";
        
        $sqlNotif = "INSERT INTO NOTIFICATIONS (USER_ID, SENDER_ID, TYPE, LISTING_ID, MESSAGE) VALUES (?, ?, 'LIKE', ?, ?)";
        db_query($conn, $sqlNotif, [$ownerId, $userId, $listingId, $msg]);
    }
}
// ────────────────────────────────────────────────────────────────────────────

$reactionCounts = listing_reaction_counts($conn, $listingId);

echo json_encode([
    'reacted' => $selectedReaction !== null,
    'liked' => $selectedReaction !== null,
    'reaction' => $selectedReaction,
    'counts' => $reactionCounts['types'],
    'count' => $reactionCounts['total']
]);

db_close($conn);
