<?php
require_once __DIR__ . '/db.php';
$conn = db_connect();

header('Content-Type: application/json');

$type  = $_GET['type'] ?? '';
$value = trim($_GET['value'] ?? '');

if (empty($type) || empty($value)) {
    echo json_encode(['available' => true]);
    exit;
}

$sql = "";
if ($type === 'stdnum') {
    $sql = "SELECT COUNT(*) as count FROM USERS WHERE STD_NUM = ?";
} elseif ($type === 'email') {
    $sql = "SELECT COUNT(*) as count FROM USERS WHERE EMAIL = ?";
} elseif ($type === 'username') {
    $sql = "SELECT COUNT(*) as count FROM USERS WHERE USERNAME = ?";
} else {
    echo json_encode(['available' => true]);
    exit;
}

$result = db_query($conn, $sql, [$value]);
$row = db_fetch_assoc($result);

echo json_encode([
    'available' => ($row['count'] == 0)
]);

db_close($conn);
