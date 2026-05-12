<?php
declare(strict_types=1);

const APP_TIMEZONE = 'Asia/Manila';
const DB_TIMEZONE_OFFSET = '+08:00';

date_default_timezone_set(APP_TIMEZONE);

function db_config(): array {
    // Check for environment variables first (common in Docker/Production)
    $env_host     = getenv('DB_HOST');
    $env_database = getenv('DB_NAME');
    $env_user     = getenv('DB_USER');
    $env_pass     = getenv('DB_PASSWORD');

    if ($env_host) {
        return [
            'host'     => $env_host,
            'database' => $env_database ?: 'pipeline_db',
            'user'     => $env_user ?: 'app_user',
            'password' => $env_pass ?: 'app_password'
        ];
    }

    // Detect if we are running locally or on the production server
    $http_host = $_SERVER['HTTP_HOST'] ?? '';
    $is_cli = (php_sapi_name() === 'cli');
    $is_docker = ($http_host === 'localhost:9090' || $is_cli); // Assume docker/local if CLI for testing
    $is_localhost = $is_docker || 
                   ($http_host === 'localhost' || 
                    $http_host === '127.0.0.1');

    if ($is_localhost) {
        return [
            'host'     => $is_docker ? 'db' : '127.0.0.1',
            'database' => 'pipeline_db',
            'user'     => $is_docker ? 'app_user' : 'root',
            'password' => $is_docker ? 'app_password' : '' 
        ];
    } else {
        // Hostinger Production Settings
        return [
            'host'     => 'localhost',
            'database' => 'u299531047_pipeline_db',
            'user'     => 'u299531047_myuser',
            'password' => 'HelloWorld123_1'
        ];
    }
}

function db_connect() {
    $config = db_config();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']);

    try {
        $conn = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $conn->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
        return $conn;
    } catch (PDOException $e) {
        db_last_error($e->getMessage());
        return false;
    }
}

function db_normalize_params(array $params): array {
    foreach ($params as $index => $value) {
        if ($value instanceof DateTimeInterface) {
            $params[$index] = $value->format('Y-m-d H:i:s');
        }
    }

    return $params;
}

function db_query($conn, string $sql, array $params = []) {
    if (!$conn) {
        return false;
    }

    try {
        $stmt = $conn->prepare($sql);
        $params = db_normalize_params($params);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        db_last_error($e->getMessage());
        return false;
    }
}

function db_fetch_assoc($stmt) {
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

/**
 * Centrally log login attempts and status changes
 */
function log_audit($conn, $userId, $attemptedIdentifier, $status) {
    // 1. Ensure table exists (fail-safe)
    db_query($conn, "CREATE TABLE IF NOT EXISTS AUDIT_LOGINS (
        LOG_ID INT AUTO_INCREMENT PRIMARY KEY,
        USER_ID INT NULL,
        USERNAME_ATTEMPT VARCHAR(255) NOT NULL,
        IP_ADDRESS VARCHAR(50) NOT NULL,
        STATUS VARCHAR(20) NOT NULL,
        CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (USER_ID) REFERENCES USERS(USER_ID) ON DELETE SET NULL
    )");

    // 2. Perform the insert
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    return db_query($conn, 
        "INSERT INTO AUDIT_LOGINS (USER_ID, USERNAME_ATTEMPT, IP_ADDRESS, STATUS) VALUES (?, ?, ?, ?)", 
        [$userId, $attemptedIdentifier, $ip, $status]
    );
}

function db_fetch($stmt): bool {
    if (!$stmt) {
        return false;
    }

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function db_last_insert_id($conn) {
    return $conn->lastInsertId();
}

function db_last_error(?string $message = null): string {
    static $lastError = '';
    if ($message !== null) {
        $lastError = $message;
    }
    return $lastError;
}

function db_close(&$conn): void {
    $conn = null;
}

function db_begin_transaction($conn): bool {
    return $conn ? $conn->beginTransaction() : false;
}

function db_commit($conn): bool {
    return $conn ? $conn->commit() : false;
}

function db_rollback($conn): bool {
    return $conn ? $conn->rollBack() : false;
}


function db_fetch_value($result, $key, $default = null) {
    $row = db_fetch_assoc($result);
    return ($row && isset($row[$key])) ? $row[$key] : $default;
}
