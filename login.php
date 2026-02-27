<?php
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "pipeline_db",
    "Uid" => "", 
    "PWD" => "",
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
$std_num = $_POST['stdnum'];
$password = $_POST['password'];

$sql = "SELECT *
        FROM dbo.[USERS] 
        WHERE STD_NUM = '$stdnum'";
$result = sqlsrv_query($conn, $sql);
$rowname = sqlsrv_fetch_array($result);
if ($rowname == null) {
    echo "<script>
                alert('Student Number not found!');
                window.history.back();
              </script>";
        exit;
}

$sqlpassword = "SELECT *
                FROM dbo.[USERS] 
                WHERE STD_NUM = '$stdnum' AND PASSWORD = '$password'";
$resultpassword = sqlsrv_query($conn, $sqlpassword);
$rowpassword = sqlsrv_fetch_array($resultpassword);
if ($rowpassword == null) {
    echo "<script>
                alert('Wrong Password!');
                window.history.back();
              </script>";
        exit;
}
$loginId = $rowpassword['USER_ID'];

$sqlprofile = "SELECT *
                FROM dbo.[USER_IMG] 
                WHERE USER_ID = '$std_num'";
$resultprofile = sqlsrv_query($conn, $sqlprofile);
if ($resultprofile === false) {
    die("PROFILE QUERY ERROR:<br>" . print_r(sqlsrv_errors(), true));
}

$rowprofile = sqlsrv_fetch_array($resultprofile);
$file_path = $rowprofile['FILE_PATH'];
?>