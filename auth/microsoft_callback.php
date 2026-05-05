<?php
// Handles the callback from Microsoft after the user logs in
session_start();
require_once __DIR__ . '/microsoft_config.php';
require_once __DIR__ . '/../db.php';

// ── 1. Validate CSRF state token ─────────────────────────────────────────────
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    $received = $_GET['state'] ?? 'NONE';
    $stored = $_SESSION['oauth_state'] ?? 'NONE';
    die("Invalid state parameter. <br>Received: $received <br>Stored: $stored <br>Possible CSRF attack or session lost.");
}
unset($_SESSION['oauth_state']);

// ── 2. Check for error from Microsoft ────────────────────────────────────────
if (isset($_GET['error'])) {
    die('Microsoft login error: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']));
}
if (!isset($_GET['code'])) {
    die('No authorization code received from Microsoft.');
}

// ── 3. Exchange the code for an access token ─────────────────────────────────
$ch = curl_init(MS_TOKEN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => MS_CLIENT_ID,
    'client_secret' => MS_CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => MS_REDIRECT_URI,
    'scope'         => 'openid profile email User.Read',
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResponse === false) {
    die('cURL error during token exchange: ' . curl_error($ch));
}

$tokenData = json_decode($tokenResponse, true);

if ($httpCode !== 200) {
    die('Failed to get access token (HTTP ' . $httpCode . '): ' . ($tokenData['error_description'] ?? $tokenResponse));
}

if (empty($tokenData['access_token'])) {
    die('Access token missing from response.');
}

// ── 4. Get the user's profile from Microsoft Graph ───────────────────────────
$ch = curl_init(MS_GRAPH_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token']
]);

$graphResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($graphResponse === false) {
    die('cURL error during profile fetch: ' . curl_error($ch));
}

if ($httpCode !== 200) {
    die('Failed to fetch user profile (HTTP ' . $httpCode . '): ' . $graphResponse);
}

$msUser  = json_decode($graphResponse, true);
$msEmail = strtolower(trim($msUser['mail'] ?? $msUser['userPrincipalName'] ?? ''));

if (empty($msEmail)) {
    die('Could not retrieve email address from Microsoft account.');
}

// ── 5. Match the email to a user in your database ────────────────────────────
$conn = db_connect();
if (!$conn) {
    die('Database connection failed.');
}

// Adjust 'EMAIL' below if your column has a different name (e.g. SCHOOL_EMAIL)
$stmt = db_query($conn, "SELECT * FROM USERS WHERE LOWER(EMAIL) = ?", [$msEmail]);
$user = db_fetch_assoc($stmt);

if (!$user) {
    // No account found — Auto-register the user
    $firstName = $msUser['givenName'] ?? '';
    $lastName  = $msUser['surname'] ?? '';
    $email     = $msEmail;
    // Generate a default username from the email
    $username  = explode('@', $email)[0];
    $password  = ''; // No password needed for MS login
    $stdNum    = $msUser['employeeId'] ?? 0; // Default placeholder for INT column   
    $cys       = $msUser['department'] ?? 'N/A'; 
    $sex       = 'Prefer Not To Say';

    $sql = "INSERT INTO USERS (FIRST_NAME, LAST_NAME, STD_NUM, CYS, SEX, USERNAME, EMAIL, `PASSWORD`, DATE_REGISTERED)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $insertResult = db_query($conn, $sql, [$firstName, $lastName, $stdNum, $cys, $sex, $username, $email, $password]);
    if ($insertResult === false) {
        die('Failed to create account: ' . db_last_error());
    }

    // Fetch the newly created user
    $stmt = db_query($conn, "SELECT * FROM USERS WHERE LOWER(EMAIL) = ?", [$msEmail]);
    $user = db_fetch_assoc($stmt);

    $isFirstLogin = true;
} else {
    $isFirstLogin = false;
}

// ── 6. Log the user in ───────────────────────────────────────────────────────
$_SESSION['user_id'] = $user['USER_ID'];

// ── 7. Fetch & Save Profile Picture from Microsoft ────────────────────────────
$ch = curl_init(MS_PHOTO_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token']
]);

$photoData = curl_exec($ch);
$photoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Only proceed if Microsoft returned an image (HTTP 200)
if ($photoHttpCode === 200 && !empty($photoData)) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'ms_profile_' . $user['USER_ID'] . '.jpg';
    $filePath = 'uploads/' . $fileName; // Relative to project root
    $fullPath = $uploadDir . $fileName; // Relative to this script

    if (file_put_contents($fullPath, $photoData)) {
        // Check if user already has an image entry
        $stmtImg = db_query($conn, "SELECT * FROM USER_IMG WHERE USER_ID = ?", [$user['USER_ID']]);
        if (db_fetch($stmtImg)) {
            // Update existing entry
            db_query($conn, "UPDATE USER_IMG SET IMG_NAME = ?, FILE_PATH = ? WHERE USER_ID = ?", [$fileName, $filePath, $user['USER_ID']]);
        } else {
            // Insert new entry
            db_query($conn, "INSERT INTO USER_IMG (IMG_NAME, FILE_PATH, USER_ID) VALUES (?,?,?)", [$fileName, $filePath, $user['USER_ID']]);
        }
    }
}

db_close($conn);

if ($isFirstLogin) {
    header('Location: ms_success.php');
} else {
    header('Location: ../dashboard.php');
}
exit;
