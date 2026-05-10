<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if (!$conn) die("Database connection failed. Check your db.php settings.");

echo "<h2>Pipeline System Diagnostics</h2>";

// 1. Check Test Account
$stmt = db_query($conn, "SELECT * FROM USERS WHERE STD_NUM = '123456789'");
$user = db_fetch_assoc($stmt);
echo "<h3>1. Test Account Check</h3>";
if ($user) {
    echo "✅ Account '123456789' found.<br>";
    if (password_verify('12345678', $user['PASSWORD'])) {
        echo "✅ Password '12345678' is VALID.<br>";
    } else {
        echo "❌ Password '12345678' is INVALID. (Password in DB: " . $user['PASSWORD'] . ")<br>";
    }
} else {
    echo "❌ Test account '123456789' NOT FOUND in database.<br>";
    echo "<a href='create_test_account.php'>Click here to try creating it again</a><br>";
}

// 2. Check Notifications Table
echo "<h3>2. Notifications Table Check</h3>";
$res = $conn->query("SHOW TABLES LIKE 'NOTIFICATIONS'");
if ($res->rowCount() > 0) {
    echo "✅ Table 'NOTIFICATIONS' exists.<br>";
    $res2 = $conn->query("SELECT COUNT(*) FROM NOTIFICATIONS");
    echo "📊 Total notifications in DB: " . $res2->fetchColumn() . "<br>";
} else {
    echo "❌ Table 'NOTIFICATIONS' IS MISSING!<br>";
    echo "<a href='create_notifications_table.php'>Click here to try creating it again</a><br>";
}

// 3. Check for recent errors
echo "<h3>3. Recent Error Logs</h3>";
if (file_exists(__DIR__ . '/../reg_db_error.log')) {
    echo "⚠️ Database Errors found in reg_db_error.log:<br>";
    echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/../reg_db_error.log')) . "</pre>";
} else {
    echo "✅ No recent database error logs found.<br>";
}

?>
