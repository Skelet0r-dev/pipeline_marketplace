<?php
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "PracticeDatabase",
    "Uid" => "", 
    "PWD" => "",
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT *
        FROM dbo.[LOGIN] 
        WHERE EMAIL = '$username'";
$result = sqlsrv_query($conn, $sql);
$rowname = sqlsrv_fetch_array($result);
if ($rowname == null) {
    echo "<script>
                alert('Username not found!');
                window.history.back();
              </script>";
        exit;
}

$sqlpassword = "SELECT *
                FROM dbo.[LOGIN] 
                WHERE EMAIL = '$username' AND PASS = '$password'";
$resultpassword = sqlsrv_query($conn, $sqlpassword);
$rowpassword = sqlsrv_fetch_array($resultpassword);
if ($rowpassword == null) {
    echo "<script>
                alert('Wrong Password!');
                window.history.back();
              </script>";
        exit;
}
$loginId = $rowpassword['LOGIN_ID'];

$sqlprofile = "SELECT *
                FROM dbo.[IMAGES] 
                WHERE LOGIN_ID = '$loginId'";
$resultprofile = sqlsrv_query($conn, $sqlprofile);
if ($resultprofile === false) {
    die("PROFILE QUERY ERROR:<br>" . print_r(sqlsrv_errors(), true));
}

$rowprofile = sqlsrv_fetch_array($resultprofile);
$file_path = $rowprofile['FILE_PATH'];
$imageName = $rowprofile['IMAGE_NAME'];
$dateandtime = $rowprofile['UPLOAD_DATE'];



?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Homepage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  </head>
  <style>

    .white-text {
      color: white;
    }


  </style>
  <body class="bg-dark">


    <section class="p-5 text-center bg-dark white-text">
      <div class="container">
        <h1 class="mb-3">Hello <?php echo ($username); ?></h1>
        <h4 class="mb-3">You have successfully logged in.</h4>
      </div>
    </section>

    
    <section class="m-5 text-center bg-dark white-text">
      <div class="container">
        <h2 class="mb-3">Your Profile Picture</h2>
      </div>

    <div class="d-flex justify-content-center">
    <div class="card bg-dark text-white center" style="width: 18rem;">
        <img src="<?php echo ($file_path); ?>" class="card-img-top" alt="...">
        <div class="card-body">
            <h5 class="card-title"><?php echo ($imageName); ?></h5>
            <p class="card-text">Uploaded on: <?php echo date_format($dateandtime, 'Y-m-d H:i:s'); ?></p>
            <div>
                <a href="homepage.php" class="btn btn-primary">Logout</a>
        </div>
        </div>
    </div>
    </section>





    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>

<html>

</html>