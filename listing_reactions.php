<?php
declare(strict_types=1);

const LISTING_REACTION_DEFAULT = 'like';

function listing_reaction_options(): array {
    return [
        'like' => [
            'label' => 'Like',
            'emoji' => '&#128077;',
        ],
        'heart_eyes' => [
            'label' => 'Heart eyes',
            'emoji' => '&#128525;',
        ],
        'thumbs_down' => [
            'label' => 'Thumbs down',
            'emoji' => '&#128078;',
        ],
    ];
}

function listing_reaction_types(): array {
    return array_keys(listing_reaction_options());
}

function normalize_listing_reaction(?string $reaction): string {
    $reaction = trim((string)$reaction);
    return in_array($reaction, listing_reaction_types(), true)
        ? $reaction
        : LISTING_REACTION_DEFAULT;
}

function listing_reactions_ensure_schema($conn): void {
    static $checked = false;
    if($checked || !$conn){
        return;
    }

    $checked = true;
    $res = db_query(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'LISTING_LIKES'
           AND COLUMN_NAME = 'REACTION_TYPE'"
    );
    $row = db_fetch_assoc($res);

    if((int)($row['CNT'] ?? 0) === 0){
        db_query(
            $conn,
            "ALTER TABLE LISTING_LIKES
             ADD COLUMN REACTION_TYPE VARCHAR(30) NOT NULL DEFAULT 'like' AFTER USER_ID"
        );
    }
}

function listing_reaction_counts($conn, int $listingId): array {
    $counts = array_fill_keys(listing_reaction_types(), 0);
    $total = 0;

    $res = db_query(
        $conn,
        "SELECT REACTION_TYPE, COUNT(*) AS CNT
         FROM LISTING_LIKES
         WHERE LISTING_ID=?
         GROUP BY REACTION_TYPE",
        [$listingId]
    );

    while($row = db_fetch_assoc($res)){
        $type = normalize_listing_reaction($row['REACTION_TYPE'] ?? '');
        $counts[$type] += (int)($row['CNT'] ?? 0);
        $total += (int)($row['CNT'] ?? 0);
    }

    return [
        'types' => $counts,
        'total' => $total,
    ];
}

function listing_user_reaction($conn, int $listingId, int $userId): ?array {
    $res = db_query(
        $conn,
        "SELECT LIKE_ID, REACTION_TYPE
         FROM LISTING_LIKES
         WHERE LISTING_ID=? AND USER_ID=?
         ORDER BY LIKE_ID DESC
         LIMIT 1",
        [$listingId, $userId]
    );
    $row = db_fetch_assoc($res);
    if(!$row){
        return null;
    }

    $row['REACTION_TYPE'] = normalize_listing_reaction($row['REACTION_TYPE'] ?? '');
    return $row;
}

function listing_reaction_users($conn, int $listingId, string $reactionType): array {
    $reactionType = normalize_listing_reaction($reactionType);
    $res = db_query(
        $conn,
        "SELECT LL.CREATED_AT,
                U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.USERNAME,
                UI.FILE_PATH AS AVATAR
         FROM LISTING_LIKES LL
         JOIN USERS U ON LL.USER_ID = U.USER_ID
         LEFT JOIN USER_IMG UI ON LL.USER_ID = UI.USER_ID
         WHERE LL.LISTING_ID=? AND LL.REACTION_TYPE=?
         ORDER BY LL.CREATED_AT DESC, LL.LIKE_ID DESC",
        [$listingId, $reactionType]
    );

    $users = [];
    while($row = db_fetch_assoc($res)){
        $createdAt = $row['CREATED_AT'] ?? null;
        $row['CREATED_AT'] = $createdAt instanceof DateTimeInterface
            ? $createdAt->format('M d, Y g:i A')
            : ($createdAt ? date('M d, Y g:i A', strtotime((string)$createdAt)) : '');
        $users[] = $row;
    }

    return $users;
}
