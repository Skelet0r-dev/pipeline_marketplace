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

    // Get next IMG_ID
    $sqlimgid = "SELECT MAX(IMG_ID) AS MAX_ID FROM dbo.[USER_IMG]";
    $resultimgid = sqlsrv_query($conn, $sqlimgid);
    $rowimgid = sqlsrv_fetch_array($resultimgid, SQLSRV_FETCH_ASSOC);
    $imgid = $rowimgid["MAX_ID"]+1;

    // Image Upload
    $destination = "uploads/";
    $imageName = basename($_FILES['image']['name']);
    $targetimagePath = $destination . $imageName;

    $allowTypes = array('jpg');
    $checkImage = pathinfo($targetimagePath, PATHINFO_EXTENSION);

    if (in_array(strtolower($checkImage), $allowTypes)) {
        $movetoUploads = move_uploaded_file($_FILES['image']['tmp_name'], $targetimagePath);
        if ($movetoUploads == true) {
            $insertIMAGES = "INSERT INTO USER_IMG ([IMG_ID], [IMG_NAME], [FILE_PATH], [USER_ID]) VALUES
                                                ('$imgid', '$imageName', '$targetimagePath', '$id')";
            $resultIMAGES = sqlsrv_query($conn, $insertIMAGES);
            if ($resultIMAGES == false) {
                die(print_r(sqlsrv_errors(), true));
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis.css">
</head>
<body>

    <div class="container mt-4">
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
    </div>
    <div>
        <hr class="hr-style">
    </div>

    <div class="container d-flex justify-content-between align-items-center" style="padding-top: 50px;">

        <!-- Left side: Uploaded Image Preview -->
        <div class="col-md-5 text-center">
            <?php if (in_array(strtolower($checkImage), $allowTypes)): ?>
                <img src="<?php echo $targetimagePath; ?>" class="img-regis img-fluid" alt="Uploaded Photo">
            <?php else: ?>
                <img src="assets/img/regis_img.png" class="img-regis img-fluid" alt="regis_img">
            <?php endif; ?>
        </div>

        <!-- Right side: Summary -->
        <div class="col-md-6">
            <h1 class="text-start mb-4 h1">Account Created</h1>

            <div class="mb-2">
                <span class="field-label">Name:</span> <?php echo $firstname . ' ' . $lastname; ?>
            </div>
            <div class="mb-2">
                <span class="field-label">Student Number:</span> <?php echo $std_number; ?>
            </div>
            <div class="mb-2">
                <span class="field-label">Course Section:</span> <?php echo $cys; ?>
            </div>
            <div class="mb-2">
                <span class="field-label">Username:</span> <?php echo $username; ?>
            </div>
            <div class="mb-2">
                <span class="field-label">Email:</span> <?php echo $email; ?>
            </div>
             <p class="mt-4">Your account has been successfully created!</p>
            <a href="login.html" class="btn btn-primary w-50 btn-create">OK</a>
        </div>
    </div>
     


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>