<?php
declare(strict_types=1);

function db_config(): array {
    // Defaults are for local development; override with environment variables in production.
    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' => getenv('DB_NAME') ?: 'pipeline_db',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: ''
    ];
}

function db_connect() {
    $config = db_config();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']);

    try {
        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
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

function db_fetch($stmt): bool {
    if (!$stmt) {
        return false;
    }

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function db_last_error(string $message = null): string {
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
