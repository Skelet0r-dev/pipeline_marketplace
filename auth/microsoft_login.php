<?php
// Starts the Microsoft OAuth flow — redirects user to Microsoft login page
session_start();
require_once __DIR__ . '/microsoft_config.php';

// Generate a random state token to prevent CSRF attacks
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'     => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri'  => MS_REDIRECT_URI,
    'response_mode' => 'query',
    'scope'         => 'openid profile email User.Read',
    'state'         => $state,
]);

header('Location: ' . MS_AUTH_URL . '?' . $params);
exit;
