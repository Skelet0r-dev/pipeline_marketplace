<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$conn = db_connect();
if (!$conn) {
    die('Database connection failed.');
}

$userId = $_SESSION['user_id'];
$stmt = db_query($conn, "SELECT * FROM USERS WHERE USER_ID = ?", [$userId]);
$user = db_fetch_assoc($stmt);

if (!$user) {
    db_close($conn);
    header('Location: ../login.html');
    exit;
}

$stmtImg = db_query($conn, "SELECT * FROM USER_IMG WHERE USER_ID = ?", [$userId]);
$userImg = db_fetch_assoc($stmtImg);

$imagepath = '';
if ($userImg && !empty($userImg['FILE_PATH'])) {
    $imagepath = '../' . $userImg['FILE_PATH']; // e.g. ../uploads/ms_profile_123.jpg
}

$firstname = $user['FIRST_NAME'] ?? '';
$lastname  = $user['LAST_NAME'] ?? '';
$stdnum    = (isset($user['STD_NUM']) && $user['STD_NUM'] != 0) ? $user['STD_NUM'] : 'N/A';
$cys       = $user['CYS'] ?? 'N/A';
$sex       = $user['SEX'] ?? 'Prefer Not To Say';
$username  = $user['USERNAME'] ?? '';
$email     = $user['EMAIL'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Linked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/regis_success.css">

</head>
<body>

<!-- Account Linked Card-->
    <div class="id-card">
        <!-- scan line sweeps after card enters -->
        <div class="scan"></div>

        <div class="id-card-header">
            <img src="../assets/img/pipeline_logo_light.png" class="brand-logo" alt="Pipeline">
            <div class="card-title-block">
                <div class="date-issue"> Date of issue <?php echo date("m. d. Y"); ?></div>
                <div class="card-title">ACCOUNT LINKED</div>
            </div>
        </div>

        <div class="id-card-body">

            <div class="id-photo-box">
                <?php if($imagepath != ''): ?>
                    <img src="<?php echo htmlspecialchars($imagepath); ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="../assets/img/regis_img.png" alt="Profile Photo">
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
            <a href="../dashboard.php">OK</a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php db_close($conn); ?>
