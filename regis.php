<?php
 $serverName = ".\SQLEXPRESS";
    $connectionOptions = [
        "Database" => "pipeline_db",
        "Uid" => "", 
        "PWD" => "",
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if (isset($_POST['submit'])) {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        echo "<script>
                alert('Passwords do not match!');
                window.history.back();
              </script>";
        exit;
    }

    if (empty($password) || empty($confirm) || empty($_POST['fullname']) || empty($_POST['username'])) {
        echo "<script>
                alert('Please fill in all fields!');
                window.history.back();
              </script>";
        exit;
    }
}

    if (!$conn) {
        die("Connection failed: " . sqlsrv_errors());
    }

    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $profile = $_FILES['profile']['name'];

    $sqlcheck = "SELECT * 
                 FROM dbo.[LOGIN] 
                 WHERE [EMAIL] = '$username'";
    $resultcheck = sqlsrv_query($conn, $sqlcheck);
    if ($resultcheck === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    if (sqlsrv_fetch($resultcheck) === true) {
        echo "<script>
                alert('Username Already Taken');
                window.history.back();
              </script>";
        exit;
    } else {
        echo "Registration Success";
    }
    $sqlinsert = "INSERT INTO dbo.[LOGIN] ([FULL_NAME], [EMAIL], [PASS]) 
                  VALUES ('$fullname', '$username', '$password')";
    $resultinsert = sqlsrv_query($conn, $sqlinsert);
    if ($resultinsert === false) {
        die(print_r(sqlsrv_errors(), true));
    } 


    $sqlid = "SELECT LOGIN_ID 
             FROM dbo.[LOGIN] 
             WHERE [EMAIL] = '$username'";
    $resultid = sqlsrv_query($conn, $sqlid);
    if ($resultid === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    $row = sqlsrv_fetch_array($resultid,);
    $id = $row['LOGIN_ID'];


#Image Upload
$destination = "uploads/";
$imageName = basename($_FILES['profile']['name']);
$targetimagePath = $destination.$imageName;


$allowTypes = array('jpg');
$checkImage = pathinfo($targetimagePath, PATHINFO_EXTENSION);


if (in_array(strtolower($checkImage), $allowTypes)) {
    $movetoUploads = move_uploaded_file($_FILES['profile']['tmp_name'], $targetimagePath);
    if ($movetoUploads == true) {
        $insertIMAGES = "INSERT INTO IMAGES ([IMAGE_NAME], [FILE_PATH], [UPLOAD_DATE], [LOGIN_ID]) VALUES
                                            ('$imageName', '$targetimagePath', GETDATE(), '$id')";
        $resultIMAGES = sqlsrv_query($conn, $insertIMAGES);
        if ($resultIMAGES == false) {
            die(print_r(sqlsrv_errors(), true)); 
        } else{
            echo "<script>
                    alert('Registration Successful');
                    window.location.href = 'homepage.php';
                  </script>";
        }
    }
}


   

?>
