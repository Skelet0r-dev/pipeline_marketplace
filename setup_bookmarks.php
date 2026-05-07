<?php
require_once __DIR__ . '/db.php';
$conn = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS LISTING_SAVED (
    SAVE_ID INT AUTO_INCREMENT PRIMARY KEY,
    LISTING_ID INT NOT NULL,
    USER_ID INT NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID),
    FOREIGN KEY (LISTING_ID) REFERENCES LISTINGS(LISTING_ID),
    UNIQUE KEY (USER_ID, LISTING_ID)
)";

try {
    db_query($conn, $sql);
    echo "Table LISTING_SAVED created successfully or already exists.";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}

db_close($conn);
