<?php
session_start();

$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "pipeline_db",
    "Uid" => "",
    "PWD" => ""
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn == false) {
    die(print_r(sqlsrv_errors(), true));
}

// POST
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($username === '' || $password === '') {
    echo "
    <script>
        alert('Please enter both admin username and password.');
        window.location.href = 'login_admin.html';
    </script>
    ";
    exit();
}

// VALIDATION
$sql_admin = "SELECT TOP 1 ADMIN_NUMBER, USERNAME, PASSWORD
              FROM dbo.[ADMIN_LOGIN]
              WHERE UPPER(LTRIM(RTRIM(USERNAME))) = UPPER(?)
                AND LTRIM(RTRIM(PASSWORD)) = ?";
$result_admin = sqlsrv_query($conn, $sql_admin, [$username, $password]);
$row_admin = $result_admin ? sqlsrv_fetch_array($result_admin, SQLSRV_FETCH_ASSOC) : false;

// LOGIN SUCCESS
if ($row_admin) {
    session_regenerate_id(true);
    $_SESSION['admin_username'] = $row_admin['USERNAME'];
    $_SESSION['admin_number'] = $row_admin['ADMIN_NUMBER'] ?? null;
    header("Location: admin_dashboard.php");
    exit();
}

// LOGIN FAILED
echo "
<script>
    alert('Invalid admin credentials. Please try again.');
    window.location.href = 'login_admin.html';
</script>
";
exit();
?>
