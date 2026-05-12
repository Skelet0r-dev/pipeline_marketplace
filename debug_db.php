<?php
require_once 'db.php';
$config = db_config();

echo "<h3>Database Connection Debug</h3>";
echo "<b>Host:</b> " . $config['host'] . "<br>";
echo "<b>Database:</b> " . $config['database'] . "<br>";
echo "<b>User:</b> " . $config['user'] . "<br>";
echo "<b>Detected Environment:</b> " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'CLI') . "<br><br>";

$conn = db_connect();

if ($conn) {
    echo "<span style='color: green;'><i class="bi bi-check"></i> Connection Successful!</span>";
} else {
    echo "<span style='color: red;'><i class="bi bi-x"></i> Connection Failed!</span><br>";
    echo "<b>Error:</b> " . db_last_error();
}
?>
