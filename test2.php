<?php
$dsn = 'mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=pipeline_db;charset=utf8mb4';
$user = 'app_user';
$password = 'app_password';

try {
    $conn = new PDO($dsn, $user, $password);
    $stmt = $conn->query("SELECT LISTING_ID, STATUS, TITLE FROM LISTINGS");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($rows) . " rows:\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
