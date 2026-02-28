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

    // Image Upload
    $allowTypes = array('jpg');
    $checkImage = '';
    $targetimagePath = '';

    if (!empty($_FILES['image']['name'])) {
        $destination = "uploads/";
        $imageName = basename($_FILES['image']['name']);
        $targetimagePath = $destination . $imageName;
        $checkImage = pathinfo($targetimagePath, PATHINFO_EXTENSION);

        if (in_array(strtolower($checkImage), $allowTypes)) {
            $movetoUploads = move_uploaded_file($_FILES['image']['tmp_name'], $targetimagePath);
            if ($movetoUploads == true) {
                // Get next IMG_ID
                $sqlimgid = "SELECT MAX(IMG_ID) AS MAX_ID FROM dbo.[USER_IMG]";
                $resultimgid = sqlsrv_query($conn, $sqlimgid);
                $rowimgid = sqlsrv_fetch_array($resultimgid, SQLSRV_FETCH_ASSOC);
                $imgid = $rowimgid["MAX_ID"] + 1;

                $insertIMAGES = "INSERT INTO USER_IMG ([IMG_ID], [IMG_NAME], [FILE_PATH], [USER_ID]) VALUES
                                                ('$imgid', '$imageName', '$targetimagePath', '$id')";
                $resultIMAGES = sqlsrv_query($conn, $insertIMAGES);
                if ($resultIMAGES == false) {
                    die(print_r(sqlsrv_errors(), true));
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis_success.css">
</head>
<body>

    <div class="id-card">

        <!-- Header -->
        <div class="id-card-header">
            <div class="brand">PIPELINE</div>
            <div class="card-title-block">
                <div class="date-issue">&#10022; Date of issue <?php echo date("m. d. Y"); ?> &#10022;</div>
                <div class="card-title">ACCOUNT CREATED</div>
            </div>
        </div>

        <!-- Body -->
        <div class="id-card-body">

            <!-- Photo -->
            <div class="id-photo-box">
                <?php if ($targetimagePath != '' && in_array(strtolower($checkImage), $allowTypes)): ?>
                    <img src="<?php echo $targetimagePath; ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="assets/img/regis_img.png" alt="Profile Photo">
                <?php endif; ?>
            </div>

            <!-- Fields -->
            <div class="id-fields">
                <div class="id-field">
                    <div class="id-field-label">Name.</div>
                    <div class="id-field-value large"><?php echo strtoupper($firstname . " " . $lastname); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Student Number.</div>
                    <div class="id-field-value"><?php echo $std_number; ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Course Year Section.</div>
                    <div class="id-field-value"><?php echo $cys; ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Username.</div>
                    <div class="id-field-value"><?php echo $username; ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Email.</div>
                    <div class="id-field-value" style="font-size:14px"><?php echo $email; ?></div>
                </div>
            </div>

            <!-- Watermark -->
            <div class="id-watermark">P</div>

        </div>

        <!-- Footer -->
        <div class="id-card-footer">
            <span class="tagline">&#10022; Your campus marketplace &#10022;</span>
            <a href="login.html">OK</a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>