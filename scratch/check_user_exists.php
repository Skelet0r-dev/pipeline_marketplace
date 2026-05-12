<?php
require_once __DIR__ . '/../db.php';
$conn = db_connect();
if(!$conn) die("Connection failed: " . db_last_error());

$stdnum = '202231367'; // The one I saw in the logs earlier
echo "Searching for student: $stdnum\n";

$sql = "SELECT * FROM USERS WHERE STD_NUM = ?";
$stmt = db_query($conn, $sql, [$stdnum]);
$user = db_fetch_assoc($stmt);

if($user) {
    echo "User found!\n";
    print_r($user);
} else {
    echo "User NOT found.\n";
    
    echo "\nListing first 5 users in DB:\n";
    $res = db_query($conn, "SELECT USER_ID, STD_NUM, USERNAME FROM USERS LIMIT 5");
    while($row = db_fetch_assoc($res)) {
        print_r($row);
    }
}

db_close($conn);
?>
