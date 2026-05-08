<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/listing_reactions.php';

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;
$reactionType = normalize_listing_reaction($_GET['reaction_type'] ?? null);

if(!$listingId){
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing_id']);
    exit;
}

$conn = db_connect();
if(!$conn){
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

listing_reactions_ensure_schema($conn);
$reactionOptions = listing_reaction_options();
$reaction = $reactionOptions[$reactionType] ?? $reactionOptions[LISTING_REACTION_DEFAULT];
$users = listing_reaction_users($conn, $listingId, $reactionType);

echo json_encode([
    'reaction' => $reactionType,
    'label' => $reaction['label'],
    'emoji' => $reaction['emoji'],
    'users' => array_map(function($user){
        return [
            'user_id' => (int)$user['USER_ID'],
            'name' => trim(($user['FIRST_NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')),
            'username' => $user['USERNAME'] ?? '',
            'avatar' => $user['AVATAR'] ?: 'assets/img/avatar.png',
            'created_at' => $user['CREATED_AT'] ?? '',
        ];
    }, $users),
]);

db_close($conn);
