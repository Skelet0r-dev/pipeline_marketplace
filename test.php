<?php
require_once __DIR__ . '/db.php';
$conn = db_connect();
if (!$conn) {
    echo "Connection failed: " . db_last_error();
    exit;
}
$sqlCarousel = "SELECT L.*, I.FILE_PATH, U.FIRST_NAME, U.LAST_NAME 
                FROM LISTINGS L
                LEFT JOIN LISTING_IMG I ON L.LISTING_ID = I.LISTING_ID AND I.IS_PRIMARY = 1
                JOIN USERS U ON L.USER_ID = U.USER_ID
                WHERE (L.`STATUS` = 'Available' OR L.`STATUS` IS NULL)
                ORDER BY L.CREATED_AT DESC LIMIT 8";
$stmtCarousel = db_query($conn, $sqlCarousel);
if (!$stmtCarousel) {
    echo "Query failed: " . db_last_error();
    exit;
}
$rows = [];
while($row = db_fetch_assoc($stmtCarousel)) {
    $rows[] = $row;
}
echo "Found " . count($rows) . " items.";
print_r($rows);
