<?php
 $serverName = ".\SQLEXPRESS";
    $connectionOptions = [
        "Database" => "pipeline_db",
        "Uid" => "", 
        "PWD" => "",
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (!$conn) {
        die("Connection failed: " . sqlsrv_errors());
    }



    $firstname = $_POST['f_name'];
    $lastname = $_POST['l_name'];
    $std_number = $_POST['stdnum'];
    $cys = $_POST['cys'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];






    $sqlcheck = "SELECT * 
                 FROM dbo.[USERS] 
                 WHERE [STD_NUM] = '$std_number'";
    $resultcheck = sqlsrv_query($conn, $sqlcheck);
    if ($resultcheck === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    if (sqlsrv_fetch($resultcheck) === true) {
        echo "<script>
                alert('There is already an account with this Student Number');
                window.history.back();
              </script>";
        exit;
    }







    $sqlinsert = "INSERT INTO dbo.[USERS] ([FIRST_NAME], [LAST_NAME], [STD_NUM], [CYS], [USERNAME], [EMAIL], [PASSWORD]) 
                  VALUES ('$firstname', '$lastname', '$std_number', '$cys', '$username', '$email', '$password')";

    $resultinsert = sqlsrv_query($conn, $sqlinsert);
    if ($resultinsert === false) {
        die(print_r(sqlsrv_errors(), true));
    } 
    $sqlid = "SELECT USER_ID 
             FROM dbo.[USERS] 
             WHERE [STD_NUM] = '$std_number'";

    $resultid = sqlsrv_query($conn, $sqlid);
    if ($resultid === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    $row = sqlsrv_fetch_array($resultid, SQLSRV_FETCH_ASSOC);
    $id = $row['USER_ID'];


#Image Upload
$destination = "uploads/";
$imageName = basename($_FILES['image']['name']);
$targetimagePath = $destination.$imageName;


$allowTypes = array('jpg');
$checkImage = pathinfo($targetimagePath, PATHINFO_EXTENSION);


if (in_array(strtolower($checkImage), $allowTypes)) {
    $movetoUploads = move_uploaded_file($_FILES['image']['tmp_name'], $targetimagePath);
    if ($movetoUploads == true) {
        $insertIMAGES = "INSERT INTO USER_IMG ([IMG_NAME], [FILE_PATH], [USER_ID]) VALUES
                                            ('$imageName', '$targetimagePath', '$id')";
        $resultIMAGES = sqlsrv_query($conn, $insertIMAGES);
        if ($resultIMAGES == false) {
            die(print_r(sqlsrv_errors(), true)); 
        } else{
            echo "<script>
                    alert('Registration Successful');
                    window.location.href = 'login.html';
                  </script>";
        }
    }
}


   

?>
