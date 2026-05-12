<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$conn = db_connect();
if (!$conn) die("Database connection failed");

$userId = $_SESSION['user_id'];

// Fetch user details
$sql = "SELECT * FROM USERS WHERE USER_ID = ?";
$res = db_query($conn, $sql, [$userId]);
$user = db_fetch_assoc($res);

if (!$user) {
    header('Location: login.html');
    exit;
}

// Fetch user image
$sqlImg = "SELECT FILE_PATH FROM USER_IMG WHERE USER_ID = ? LIMIT 1";
$resImg = db_query($conn, $sqlImg, [$userId]);
$img = db_fetch_assoc($resImg);

$firstname  = $user['FIRST_NAME'];
$lastname   = $user['LAST_NAME'];
$stdnum     = $user['STD_NUM'];
$college    = $user['COLLEGE'];
$department = $user['DEPARTMENT'];
$section    = $user['SECTION'];
$sex        = $user['SEX'];
$username   = $user['USERNAME'];
$email      = $user['EMAIL'];
$imagepath  = $img ? $img['FILE_PATH'] : 'assets/img/regis_img.png';

// Image check logic from original regis.php
$checkimage = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));
$allowtypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created | Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/regis_success.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- Account Created Card-->
    <div class="id-card">
        <!-- scan line sweeps after card enters -->
        <div class="scan"></div>

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
                    <div class="id-field-label">College.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($college); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Department.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($department); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Section.</div>
                    <div class="id-field-value"><?php echo htmlspecialchars($section); ?></div>
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
            <a href="dashboard.php" class="btn-ok">OK</a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Trigger Browser Notification on success
        window.addEventListener('load', () => {
            if ("Notification" in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        new Notification("Account Verified! <i class="bi bi-party-popper"></i>", {
                            body: "Welcome to Pipeline, <?php echo htmlspecialchars($firstname); ?>! Your digital ID is ready.",
                            icon: "assets/img/pipeline_logo_light.png"
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php db_close($conn); ?>
