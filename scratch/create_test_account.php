<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if (!$conn) die("DB connection failed");

$stdnum = "123456789";
$email = "test@dlsud.edu.ph";
$password_plain = "12345678"; // Simpler password
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
$date_now = date('Y-m-d');

// Delete existing to reset
db_query($conn, "DELETE FROM USERS WHERE STD_NUM = ?", [$stdnum]);

$sql = "INSERT INTO USERS (FIRST_NAME, LAST_NAME, STD_NUM, COLLEGE, DEPARTMENT, SECTION, SEX, USERNAME, EMAIL, PASSWORD, VERIFIED, DATE_REGISTERED) 
        VALUES ('Test', 'User', ?, 'CICS', 'IT', 'TEST-1', 'Male', 'testuser', ?, ?, 1, ?)";
$params = [$stdnum, $email, $password_hashed, $date_now];

if (db_query($conn, $sql, $params)) {
    echo "<h1>Test Account Reset Successful!</h1>";
    echo "<p><strong>Student Number:</strong> 123456789</p>";
    echo "<p><strong>Password:</strong> 12345678</p>";
    echo "<p><a href='../login.html'>Go to Login</a></p>";
} else {
    echo "Error: " . db_last_error();
}
?>
