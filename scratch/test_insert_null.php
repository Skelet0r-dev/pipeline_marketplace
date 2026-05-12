<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if(!$conn) die("Connection failed: " . db_last_error());

$stdnum = 'test12345';
$ip = '127.0.0.1';
$uid = null;

$sql = "INSERT INTO AUDIT_LOGINS (USER_ID, USERNAME_ATTEMPT, IP_ADDRESS, STATUS) VALUES (?, ?, ?, 'TEST')";
$res = db_query($conn, $sql, [$uid, $stdnum, $ip]);

if($res) echo "Insert successful.\n";
else echo "Insert failed: " . db_last_error() . "\n";

db_close($conn);
?>
