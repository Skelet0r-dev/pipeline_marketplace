<?php
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "pipeline_db",
    "Uid" => "", 
    "PWD" => "",
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
$stdnum = $_POST['stdnum'];
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
                WHERE USER_ID = '$loginId'";
$resultprofile = sqlsrv_query($conn, $sqlprofile);
if ($resultprofile === false) {
    die("PROFILE QUERY ERROR:<br>" . print_r(sqlsrv_errors(), true));
}

$rowprofile = sqlsrv_fetch_array($resultprofile);
$file_path = $rowprofile['FILE_PATH'];
$firstname = $rowpassword['FIRST_NAME'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="body">

    <!-- Elevated Navbar -->
    <div class="dash-navbar">
        <img src="assets/img/pipeline_wireframe-removebg.png" class="img-logo" alt="Pipeline Logo">
        <div class="dash-nav-right">
            <div class="dash-greeting">
                <span class="dash-hello">Hello,</span>
                <span class="dash-name"><?php echo $firstname; ?></span>
            </div>
            <img src="<?php echo $file_path; ?>" class="img-profile" alt="Profile Picture">
        </div>
    </div>

    <div class="dash-header-bar"></div>

    <div class="container mt-4">
        <div class="row mt-4 align-items-center">

            <!-- Categories on the Left -->
            <div class="col d-flex flex-column">
                <h1 class="h1 mt-2">Everything You Need,</h1>
                <h1 class="h1">Within Campus Reach</h1>

                <!-- Row 1 -->
                <div class="d-flex gap-3 mt-4">
                    <div class="d-flex flex-column align-items-center justify-content-center square-acad">
                        <img src="assets/img/academics.svg" class="img-acad" alt="Academics Icon">
                        <p class="p-acad mb-0">Academics</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-tech">
                        <img src="assets/img/keyboard.svg" class="img-tech" alt="Keyboard Icon">
                        <p class="p-tech mb-0">Electronics and Tech</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-clothing">
                        <img src="assets/img/shirts.svg" class="img" alt="Shirt">
                        <p class="p-clothing mb-0">Clothing & Apparel</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-hobbies">
                        <img src="assets/img/labubus.svg" class="img" alt="Labubu">
                        <p class="p-hobbies mb-0">Hobbies & Lifestyle</p>
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="d-flex gap-3 mt-3">
                    <div class="d-flex flex-column align-items-center justify-content-center square-food">
                        <img src="assets/img/cookies.svg" class="img" alt="Cookies Icon">
                        <p class="p-cookies mb-0">Food</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-events">
                        <img src="assets/img/tickets.svg" class="img" alt="Tickets Icon">
                        <p class="p-events mb-0">Events & Tickets</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-specific">
                        <img src="assets/img/electronics.svg" class="img" alt="Electronics Icon">
                        <p class="p-specific mb-0">Course-Specific</p>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center square-allitems">
                        <img src="assets/img/cart.svg" class="img" alt="Cart Icon">
                        <p class="p-allitems mb-0">All Items</p>
                    </div>
                </div>
            </div>

            <!-- Video on the Right -->
            <div class="col-auto d-flex align-items-center">
                <div class="video-crop">
                    <video src="assets/img/dashboard-final.mp4" autoplay muted loop playsinline poster="thumbnail.jpg">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>