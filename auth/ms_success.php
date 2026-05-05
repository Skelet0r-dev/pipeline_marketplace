<?php
session_start();
require_once __DIR__ . '/microsoft_config.php';
require_once __DIR__ . '/../db.php';

$pending = $_SESSION['ms_pending'] ?? null;
if (!$pending) {
    header('Location: ../login.html');
    exit;
}

$errors = [];
$registered = false;
$user = null;
$imagepath = '';
$pendingPhotoSrc = '';

function ms_fetch_profile_photo(string $accessToken): ?array {
    if ($accessToken === '') {
        return null;
    }

    $ch = curl_init(MS_PHOTO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $photoData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
    curl_close($ch);

    if ($httpCode !== 200 || empty($photoData) || !@getimagesizefromstring($photoData)) {
        return null;
    }

    $contentType = strtolower(trim(explode(';', $contentType)[0]));
    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    return [
        'data' => $photoData,
        'mime' => $contentType,
        'ext'  => $extensionMap[$contentType] ?? 'jpg',
    ];
}

function ms_pending_photo(): ?array {
    $photo = $_SESSION['ms_pending_photo'] ?? null;
    if (!is_array($photo) || empty($photo['data_b64'])) {
        return null;
    }

    $data = base64_decode($photo['data_b64'], true);
    if ($data === false || !@getimagesizefromstring($data)) {
        return null;
    }

    return [
        'data' => $data,
        'mime' => $photo['mime'] ?? 'image/jpeg',
        'ext'  => $photo['ext'] ?? 'jpg',
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_SESSION['ms_pending_photo'])) {
    $photo = ms_fetch_profile_photo($pending['access_token'] ?? '');
    if ($photo) {
        $_SESSION['ms_pending_photo'] = [
            'data_b64' => base64_encode($photo['data']),
            'mime' => $photo['mime'],
            'ext' => $photo['ext'],
        ];
    }
}

$pendingPhoto = ms_pending_photo();
if ($pendingPhoto) {
    $pendingPhotoSrc = 'data:' . htmlspecialchars($pendingPhoto['mime'], ENT_QUOTES, 'UTF-8') . ';base64,' . base64_encode($pendingPhoto['data']);
}

// ── Handle POST (form submission) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $username  = trim($_POST['username']   ?? '');
    $section   = strtoupper(trim($_POST['section'] ?? ''));
    $sex       = $_POST['sex']             ?? '';

    if (empty($firstName)) $errors[] = 'First name is required.';
    if (empty($lastName))  $errors[] = 'Last name is required.';
    if (empty($username))  $errors[] = 'Username is required.';
    if (empty($section))   $errors[] = 'Section is required.';
    if (empty($sex))       $errors[] = 'Please select your sex.';

    if (empty($errors)) {
        $conn = db_connect();
        if (!$conn) die('Database connection failed.');

        // Check username uniqueness
        $stmtCheck = db_query($conn, "SELECT USER_ID FROM USERS WHERE USERNAME = ?", [$username]);
        if (db_fetch($stmtCheck)) {
            $errors[] = 'Username is already taken. Please choose another.';
        }
    }

    if (empty($errors)) {
        $email   = $pending['email'];
        $stdNum  = $pending['std_num'] ?? 0;
        $college = $pending['college'] ?? 'N/A';
        $department = $pending['department'] ?? 'N/A';

        $sql = "INSERT INTO USERS 
                    (FIRST_NAME, LAST_NAME, STD_NUM, COLLEGE, DEPARTMENT, SECTION, SEX, USERNAME, EMAIL, `PASSWORD`, DATE_REGISTERED)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', NOW())";
        db_query($conn, $sql, [$firstName, $lastName, $stdNum, $college, $department, $section, $sex, $username, $email]);

        $stmt = db_query($conn, "SELECT * FROM USERS WHERE LOWER(EMAIL) = ?", [strtolower($email)]);
        $user = db_fetch_assoc($stmt);

        $_SESSION['user_id'] = $user['USER_ID'];

        // ── Fetch & Save Profile Picture from Microsoft ───────────────────
        $photo = ms_pending_photo() ?: ms_fetch_profile_photo($pending['access_token'] ?? '');

        if ($photo) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = 'ms_profile_' . $user['USER_ID'] . '.' . $photo['ext'];
            $filePath = 'uploads/' . $fileName;
            $fullPath = $uploadDir . $fileName;

            if (file_put_contents($fullPath, $photo['data'])) {
                $stmtImg = db_query($conn, "SELECT * FROM USER_IMG WHERE USER_ID = ?", [$user['USER_ID']]);
                if (db_fetch($stmtImg)) {
                    db_query($conn, "UPDATE USER_IMG SET IMG_NAME = ?, FILE_PATH = ? WHERE USER_ID = ?", [$fileName, $filePath, $user['USER_ID']]);
                } else {
                    db_query($conn, "INSERT INTO USER_IMG (IMG_NAME, FILE_PATH, USER_ID) VALUES (?,?,?)", [$fileName, $filePath, $user['USER_ID']]);
                }
                $imagepath = '../' . $filePath;
            }
        }

        // Also check if image already exists for display
        if (empty($imagepath)) {
            $stmtImg = db_query($conn, "SELECT * FROM USER_IMG WHERE USER_ID = ?", [$user['USER_ID']]);
            $userImg = db_fetch_assoc($stmtImg);
            if ($userImg && !empty($userImg['FILE_PATH'])) {
                $imagepath = '../' . $userImg['FILE_PATH'];
            }
        }

        unset($_SESSION['ms_pending']);
        unset($_SESSION['ms_pending_photo']);
        db_close($conn);
        $registered = true;
    } else {
        if (isset($conn)) db_close($conn);
    }
}

// ── Variables for display ─────────────────────────────────────────────────────
if ($registered && $user) {
    $firstname = $user['FIRST_NAME']  ?? '';
    $lastname  = $user['LAST_NAME']   ?? '';
    $stdnum    = (isset($user['STD_NUM']) && $user['STD_NUM'] != 0) ? $user['STD_NUM'] : 'N/A';
    $college   = $user['COLLEGE']     ?? 'N/A';
    $department = $user['DEPARTMENT'] ?? 'N/A';
    $section   = $user['SECTION']     ?? '';
    $sex       = $user['SEX']         ?? 'Prefer Not To Say';
    $username  = $user['USERNAME']    ?? '';
    $email     = $user['EMAIL']       ?? '';
} else {
    // Pre-fill from session for the form
    $firstname = $_POST['first_name'] ?? ($pending['first_name'] ?? '');
    $lastname  = $_POST['last_name']  ?? ($pending['last_name']  ?? '');
    $stdnum    = (!empty($pending['std_num']) && $pending['std_num'] != 0) ? $pending['std_num'] : 'N/A';
    $college   = $pending['college']    ?? 'N/A';
    $department = $pending['department'] ?? 'N/A';
    $section   = $_POST['section'] ?? '';
    $sex       = $_POST['sex'] ?? '';
    $username  = $_POST['username'] ?? ($pending['username'] ?? '');
    $email     = $pending['email']      ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $registered ? 'Account Linked' : 'Complete Your Profile'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/regis_success.css">
    <style>
        /* ── Editable field overrides ── */
        .id-field-input {
            background: transparent;
            border: none;
            border-bottom: 1.5px solid rgba(0,0,0,0.15);
            border-radius: 0;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
            width: 100%;
            padding: 2px 0;
            outline: none;
            transition: border-color 0.2s;
        }
        .id-field-input:focus {
            border-bottom-color: #1a6b3c;
        }
        .id-field-input.large {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .id-field-select {
            background: transparent;
            border: none;
            border-bottom: 1.5px solid rgba(0,0,0,0.15);
            border-radius: 0;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
            width: 100%;
            padding: 2px 0;
            outline: none;
            appearance: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .id-field-select:focus {
            border-bottom-color: #1a6b3c;
        }
        .id-field-hint {
            font-size: 10px;
            color: #888;
            margin-top: 2px;
        }
        .id-error-banner {
            background: #fdecea;
            border-left: 3px solid #e53935;
            color: #b71c1c;
            font-size: 12px;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .id-card-footer button {
            background: #fff;
            color: #1a6b3c;
            border: none;
            border-radius: 20px;
            padding: 8px 28px;
            font-weight: 700;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.2s, color 0.2s;
        }
        .id-card-footer button:hover {
            background: #1a6b3c;
            color: #fff;
        }
    </style>
</head>
<body>

<?php if ($registered): ?>
<!-- ── ACCOUNT LINKED CARD (after successful registration) ── -->
<div class="id-card">
    <div class="scan"></div>

    <div class="id-card-header">
        <img src="../assets/img/pipeline_logo_light.png" class="brand-logo" alt="Pipeline">
        <div class="card-title-block">
            <div class="date-issue">Date of issue <?php echo date("m. d. Y"); ?></div>
            <div class="card-title">ACCOUNT LINKED</div>
        </div>
    </div>

    <div class="id-card-body">

        <div class="id-left-col">
            <div class="id-photo-box">
                <?php if (!empty($imagepath)): ?>
                    <img src="<?php echo htmlspecialchars($imagepath); ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="../assets/img/regis_img.png" alt="Profile Photo">
                <?php endif; ?>
            </div>
            <div class="id-identity-fields">
                <div class="id-field">
                    <div class="id-field-label">First Name.</div>
                    <div class="id-field-value large"><?php echo htmlspecialchars($firstname); ?></div>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Last Name.</div>
                    <div class="id-field-value large"><?php echo htmlspecialchars($lastname); ?></div>
                </div>
            </div>
        </div>

        <div class="id-fields">
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
                <div class="id-field-value" style="font-size:13px"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>

        <div class="id-watermark">P</div>

    </div>

    <div class="id-card-footer">
        <span class="tagline"></span>
        <a href="../dashboard.php">OK</a>
    </div>
</div>

<?php else: ?>
<!-- ── COMPLETE YOUR PROFILE FORM CARD ── -->
<form method="POST" action="">
<div class="id-card">
    <div class="scan"></div>

    <div class="id-card-header">
        <img src="../assets/img/pipeline_logo_light.png" class="brand-logo" alt="Pipeline">
        <div class="card-title-block">
            <div class="date-issue">Date of issue <?php echo date("m. d. Y"); ?></div>
            <div class="card-title">COMPLETE PROFILE</div>
        </div>
    </div>

    <div class="id-card-body">

        <div class="id-left-col">
            <div class="id-photo-box">
                <?php if (!empty($pendingPhotoSrc)): ?>
                    <img src="<?php echo $pendingPhotoSrc; ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="../assets/img/regis_img.png" alt="Profile Photo">
                <?php endif; ?>
            </div>
            <div class="id-identity-fields">
                <div class="id-field">
                    <div class="id-field-label">First Name.</div>
                    <input
                        type="text"
                        name="first_name"
                        class="id-field-input large"
                        value="<?php echo htmlspecialchars($firstname); ?>"
                        placeholder="Enter first name"
                        required>
                </div>
                <div class="id-field">
                    <div class="id-field-label">Last Name.</div>
                    <input
                        type="text"
                        name="last_name"
                        class="id-field-input large"
                        value="<?php echo htmlspecialchars($lastname); ?>"
                        placeholder="Enter last name"
                        required>
                </div>
            </div>
        </div>

        <div class="id-fields">

            <?php if (!empty($errors)): ?>
                <div class="id-error-banner">
                    <?php foreach ($errors as $e): ?>
                        <div>• <?php echo htmlspecialchars($e); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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
                <input
                    type="text"
                    name="section"
                    class="id-field-input"
                    value="<?php echo htmlspecialchars($section); ?>"
                    placeholder="e.g. 31"
                    required>
                <div class="id-field-hint">Enter your year and section code.</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Sex.</div>
                <select name="sex" class="id-field-select" required>
                    <option value="" disabled <?php echo ($sex === '' ? 'selected' : ''); ?>>Select sex</option>
                    <option value="Male"              <?php echo ($sex === 'Male'              ? 'selected' : ''); ?>>Male</option>
                    <option value="Female"            <?php echo ($sex === 'Female'            ? 'selected' : ''); ?>>Female</option>
                    <option value="Prefer Not To Say" <?php echo ($sex === 'Prefer Not To Say' ? 'selected' : ''); ?>>Prefer Not To Say</option>
                </select>
            </div>
            <div class="id-field">
                <div class="id-field-label">Username.</div>
                <input
                    type="text"
                    name="username"
                    class="id-field-input"
                    value="<?php echo htmlspecialchars($username); ?>"
                    placeholder="Choose a username"
                    required>
                <div class="id-field-hint">You can change this later in settings.</div>
            </div>
            <div class="id-field">
                <div class="id-field-label">Email.</div>
                <div class="id-field-value" style="font-size:13px"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>

        <div class="id-watermark">P</div>

    </div>

    <div class="id-card-footer">
        <span class="tagline"></span>
        <button type="submit">CONFIRM</button>
    </div>
</div>
</form>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>