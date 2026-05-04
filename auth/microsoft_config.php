<?php
// ─────────────────────────────────────────────────────────────────────────────
// Microsoft OAuth Config
// Fill in the values from your Azure App Registration
// ─────────────────────────────────────────────────────────────────────────────

// Load credentials securely from an ignored file
if (file_exists(__DIR__ . '/microsoft_secrets.php')) {
    require_once __DIR__ . '/microsoft_secrets.php';
} else {
    define('MS_CLIENT_ID',     'ENV_CLIENT_ID');
    define('MS_CLIENT_SECRET', 'ENV_CLIENT_SECRET');
}
define('MS_TENANT_ID',     'common');                    // Use 'common' for multi-tenant

// Must match exactly what you put in Azure → Authentication → Redirect URIs
define('MS_REDIRECT_URI', 'http://localhost:9090/auth/microsoft_callback.php');

// OAuth endpoints
define('MS_AUTH_URL',  'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/authorize');
define('MS_TOKEN_URL', 'https://login.microsoftonline.com/' . MS_TENANT_ID . '/oauth2/v2.0/token');
define('MS_GRAPH_URL', 'https://graph.microsoft.com/v1.0/me');
define('MS_PHOTO_URL', 'https://graph.microsoft.com/v1.0/me/photo/$value');
