<?php
require_once __DIR__ . '/db.php';
$conn = db_connect();
if($conn==false)
    die(db_last_error());

$firstname  = trim($_POST['f_name']);
$lastname   = trim($_POST['l_name']);
$stdnum     = trim($_POST['stdnum']);
$cys        = strtoupper(trim($_POST['cys']));
$sex        = trim($_POST['sex']);
$username   = trim($_POST['username']);
$email      = trim($_POST['email']);
$password   = $_POST['password'];

// ── Server-side validation ──
$errors = [];

if(empty($firstname))  $errors[] = "First name is required.";
if(empty($lastname))   $errors[] = "Last name is required.";
if(empty($username))   $errors[] = "Username is required.";
if(empty($sex))        $errors[] = "Sex is required.";

if(!preg_match('/^\d{9}$/', $stdnum))
    $errors[] = "Student number must be exactly 9 digits.";

if(!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = "Please enter a valid email address.";

$pwlen = strlen($password);
if($pwlen < 8 || $pwlen > 16)          $errors[] = "Password must be 8 to 16 characters.";
if(!preg_match('/[A-Z]/', $password))  $errors[] = "Password needs at least one uppercase letter.";
if(!preg_match('/[a-z]/', $password))  $errors[] = "Password needs at least one lowercase letter.";
if(!preg_match('/[0-9]/', $password))  $errors[] = "Password needs at least one number.";
if(!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = "Password needs at least one symbol.";

$allowedsex = ['Male','Female','Prefer Not To Say'];
if(!in_array($sex, $allowedsex))
    $errors[] = "Invalid value for sex.";

if(!empty($errors)){
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis.css">
</head>
<body>
    <div class="error-card">
        <h2>⚠ Registration Error</h2>
        <ul class="error-list">
            <?php foreach($errors as $err){ echo '<li>'.htmlspecialchars($err).'</li>'; } ?>
        </ul>
        <a href="javascript:history.back()" class="btn-back">← Go Back</a>
    </div>
</body>
</html>
<?php
    exit;
}

// ── Check if student number already exists ──
$sql="SELECT * FROM USERS WHERE STD_NUM=?";
$result=db_query($conn,$sql, [$stdnum]);
if($result===false) die(db_last_error());

if(db_fetch($result)===true){
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis.css">
</head>
<body>
    <div class="error-card">
        <h2>⚠ Registration Error</h2>
        <ul class="error-list">
            <li>An account with this student number already exists.</li>
        </ul>
        <a href="javascript:history.back()" class="btn-back">← Go Back</a>
    </div>
</body>
</html>
<?php
    exit;
}

// ── Insert new user ──
// ── Insert new user ──
$sql="INSERT INTO USERS (FIRST_NAME, LAST_NAME, STD_NUM, CYS, SEX, USERNAME, EMAIL, `PASSWORD`, DATE_REGISTERED)
      VALUES (?,?,?,?,?,?,?,?,NOW())";
$result=db_query($conn,$sql, [$firstname,$lastname,$stdnum,$cys,$sex,$username,$email,$password]);
if($result===false) die(db_last_error());

// ── Get new user ID ──
$sql="SELECT USER_ID FROM USERS WHERE STD_NUM=?";
$result=db_query($conn,$sql, [$stdnum]);
if($result===false) die(db_last_error());
$row=db_fetch_assoc($result);
$id=$row['USER_ID'];

// ── Image upload ──
$allowtypes = ['jpg'];
$checkimage = '';
$imagepath  = '';

if(!empty($_FILES['image']['name'])){
    $destination = "uploads/";
    $imagename   = basename($_FILES['image']['name']);
    $imagepath   = $destination.$imagename;
    $checkimage  = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));

    if(in_array($checkimage, $allowtypes)){
        if(move_uploaded_file($_FILES['image']['tmp_name'], $imagepath)){
            $sql="INSERT INTO USER_IMG (IMG_NAME, FILE_PATH, USER_ID) VALUES (?,?,?)";
            $result=db_query($conn,$sql, [$imagename,$imagepath,$id]);
            if($result===false) die(db_last_error());
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis_success.css">
</head>
<body>

    <div class="id-card">

        <div class="id-card-header">
            <img src="assets/img/pipeline_logo_light.png" class="brand-logo" alt="Pipeline">
            <div class="card-title-block">
                <div class="date-issue"> Date of issue <?php echo date("m. d. Y"); ?></div>
                <div class="card-title">ACCOUNT CREATED</div>
            </div>
        </div>

        <div class="id-card-body">

            <div class="id-photo-box">
                <?php if($imagepath != '' && in_array($checkimage, $allowtypes)): ?>
                    <img src="<?php echo htmlspecialchars($imagepath); ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="assets/img/regis_img.png" alt="Profile Photo">
                <?php endif; ?>
            </div>

            <div class="id-fields">
                <div class="id-field">
                    <div class="id-field-label">Name.</div>
                    <div class="id-field-value large"><?php echo htmlspecialchars(strtoupper($firstname.' '.$lastname)); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Student Number.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($stdnum); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Course Year Section.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($cys); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Sex.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($sex); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Username.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($username); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Email.</div>
                    <div class="id-field-value" style="font-size:14px"><?php echo htmlspecialchars($email); ?></div>
                </div>
            </div>

            <div class="id-watermark">P</div>

        </div>

        <div class="id-card-footer">
            <span class="tagline"></span>
            <a href="login.html">OK</a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php db_close($conn); ?>