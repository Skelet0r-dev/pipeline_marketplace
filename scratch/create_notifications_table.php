<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if (!$conn) die("DB failed");

$sql = "CREATE TABLE IF NOT EXISTS NOTIFICATIONS (
    NOTIFICATION_ID INT AUTO_INCREMENT PRIMARY KEY,
    USER_ID INT NOT NULL,
    SENDER_ID INT NOT NULL,
    TYPE ENUM('LIKE', 'COMMENT') NOT NULL,
    LISTING_ID INT NOT NULL,
    MESSAGE TEXT,
    IS_READ TINYINT(1) DEFAULT 0,
    CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID)
)";

if ($conn->exec($sql) !== false) {
    echo "Table created successfully";
} else {
    echo "Failed to create table: " . print_r($conn->errorInfo(), true);
}
?>
