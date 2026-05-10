<?php
require_once __DIR__ . '/db.php';
$conn = db_connect();
$conn->exec("ALTER TABLE NOTIFICATIONS ADD COLUMN IS_SHOWN_TOAST TINYINT(1) DEFAULT 0");
echo "Column added";
?>
