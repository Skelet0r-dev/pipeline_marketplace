<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if(!$conn) die("Connection failed: " . db_last_error());

$sql = "CREATE TABLE IF NOT EXISTS AUDIT_LOGINS (
    LOG_ID INT AUTO_INCREMENT PRIMARY KEY,
    USER_ID INT NULL,
    USERNAME_ATTEMPT VARCHAR(255) NOT NULL,
    IP_ADDRESS VARCHAR(50) NOT NULL,
    STATUS VARCHAR(20) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE SET NULL
)";

$result = db_query($conn, $sql);
if($result !== false) echo "Table created successfully.";
else echo "Error creating table: " . db_last_error();

db_close($conn);
?>
