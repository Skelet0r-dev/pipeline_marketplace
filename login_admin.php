<?php
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
$username = $_POST['username'];
$password = $_POST['password'];

// VALIDATION
$sql_admin = "SELECT * FROM dbo.[ADMIN_LOGIN]
              WHERE USERNAME = '$username' AND PASSWORD = '$password'";
$result_admin = sqlsrv_query($conn, $sql_admin);
$row_admin = sqlsrv_fetch_array($result_admin);

// LOGIN SUCCESS
if ($row_admin) {
    header("Location: admin_dashboard.html");
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