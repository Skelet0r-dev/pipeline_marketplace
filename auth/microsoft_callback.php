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
$httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResponse === false) die('cURL error during token exchange.');

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
$httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($graphResponse === false) die('cURL error during profile fetch.');
if ($httpCode !== 200) die('Failed to fetch user profile (HTTP ' . $httpCode . '): ' . $graphResponse);

$msUser  = json_decode($graphResponse, true);
$msEmail = strtolower(trim($msUser['mail'] ?? $msUser['userPrincipalName'] ?? ''));

if (empty($msEmail)) die('Could not retrieve email address from Microsoft account.');

// ── 5. Smart name extraction ──────────────────────────────────────────────────
// givenName/surname are often empty on school accounts — fall back to displayName
$displayName = trim($msUser['displayName'] ?? '');
$givenName   = trim($msUser['givenName']   ?? '');
$surname     = trim($msUser['surname']     ?? '');

if (empty($givenName) && empty($surname) && !empty($displayName)) {
    $nameParts = explode(' ', $displayName);
    $surname   = array_pop($nameParts);     // Last word  → Last name
    $givenName = implode(' ', $nameParts);  // Rest       → First name
}

// ── 6. Extract COLLEGE and DEPARTMENT from MS department field ───────────────
// MS department is typically: "CEAT - Computer Engineering (CPE)"
// Split on " - " to separate college abbreviation from department name
$msDepartment = trim($msUser['department'] ?? '');
if (!empty($msDepartment) && strpos($msDepartment, ' - ') !== false) {
    $parts      = explode(' - ', $msDepartment, 2);
    $college    = trim($parts[0]); // e.g. "CEAT"
    $department = trim($parts[1]); // e.g. "Computer Engineering (CPE)"
} else {
    $college    = $msDepartment ?: 'N/A';
    $department = 'N/A';
}

// ── 7. Check if user already exists ──────────────────────────────────────────
$conn = db_connect();
if (!$conn) die('Database connection failed.');

$stmt = db_query($conn, "SELECT * FROM USERS WHERE LOWER(EMAIL) = ?", [$msEmail]);
$user = db_fetch_assoc($stmt);
db_close($conn);

if ($user) {
    // Existing user — log in directly, no profile completion needed
    $_SESSION['user_id'] = $user['USER_ID'];
    header('Location: ../dashboard.php');
    exit;
}

// ── 8. New user — store MS data in session, go to profile completion form ────
$_SESSION['ms_pending'] = [
    'first_name'   => $givenName,
    'last_name'    => $surname,
    'email'        => $msEmail,
    'username'     => explode('@', $msEmail)[0],
    'std_num'      => $msUser['employeeId'] ?? '',
    'college'      => $college,
    'department'   => $department,
    'access_token' => $tokenData['access_token'],
];

header('Location: ms_success.php');
exit;