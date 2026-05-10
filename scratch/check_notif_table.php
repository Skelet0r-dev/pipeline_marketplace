<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
$res = $conn->query("SHOW TABLES LIKE 'NOTIFICATIONS'");
if ($res->rowCount() > 0) {
    echo "Table NOTIFICATIONS exists.<br>";
    $res2 = $conn->query("SELECT COUNT(*) FROM NOTIFICATIONS");
    echo "Current notification count: " . $res2->fetchColumn();
} else {
    echo "Table NOTIFICATIONS DOES NOT EXIST!";
}
?>
