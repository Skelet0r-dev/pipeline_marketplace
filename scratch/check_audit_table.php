<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if(!$conn) die("Connection failed: " . db_last_error());

echo "Checking table AUDIT_LOGINS...\n";
$res = db_query($conn, "DESCRIBE AUDIT_LOGINS");
if($res) {
    while($row = db_fetch_assoc($res)) {
        print_r($row);
    }
} else {
    echo "Table does not exist or error: " . db_last_error();
}

echo "\nChecking recent data...\n";
$res = db_query($conn, "SELECT * FROM AUDIT_LOGINS ORDER BY LOG_ID DESC LIMIT 5");
if($res) {
    while($row = db_fetch_assoc($res)) {
        print_r($row);
    }
} else {
    echo "Error fetching data: " . db_last_error();
}

db_close($conn);
?>
