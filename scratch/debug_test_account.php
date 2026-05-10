<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
$stmt = db_query($conn, "SELECT * FROM USERS WHERE STD_NUM = '123456789'");
$user = db_fetch_assoc($stmt);
if ($user) {
    echo "Account found: " . $user['FIRST_NAME'] . " " . $user['LAST_NAME'] . "<br>";
    echo "Hashed Password in DB: " . $user['PASSWORD'] . "<br>";
    if (password_verify('12345678', $user['PASSWORD'])) {
        echo "Password VERIFIES correctly!";
    } else {
        echo "Password verification FAILED!";
    }
} else {
    echo "Account NOT FOUND in database.";
}
?>
