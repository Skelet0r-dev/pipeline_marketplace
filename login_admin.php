<?php
session_start();

require_once __DIR__ . '/db.php';
$conn = db_connect();
if ($conn == false) {
    die(db_last_error());
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
$sql_admin = "SELECT ADMIN_NUMBER, USERNAME, `PASSWORD`
              FROM ADMIN_LOGIN
              WHERE UPPER(TRIM(USERNAME)) = UPPER(?)
                AND TRIM(`PASSWORD`) = ?
              LIMIT 1";
$result_admin = db_query($conn, $sql_admin, [$username, $password]);
$row_admin = $result_admin ? db_fetch_assoc($result_admin) : false;

// Log the attempt
log_audit($conn, null, $username, 'ATTEMPT');

// LOGIN SUCCESS
if ($row_admin) {
    // Log success
    log_audit($conn, null, $username, 'SUCCESS');

    session_regenerate_id(true);
    $_SESSION['admin_username'] = $row_admin['USERNAME'];
    $_SESSION['admin_number'] = $row_admin['ADMIN_NUMBER'] ?? null;
    header("Location: admin_dashboard.php");
    exit();
}

// LOGIN FAILED
// Log failure
log_audit($conn, null, $username, 'FAILED');
echo "
<script>
    alert('Invalid admin credentials. Please try again.');
    window.location.href = 'login_admin.html';
</script>
";
exit();
?>
