<?php
declare(strict_types=1);

$GLOBALS['DB_LAST_ERROR'] = '';

function db_config(): array {
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
            // Use silent errors so existing call sites can surface db_last_error without exceptions.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        $GLOBALS['DB_LAST_ERROR'] = $e->getMessage();
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

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $GLOBALS['DB_LAST_ERROR'] = implode(' ', $conn->errorInfo());
        return false;
    }

    $params = db_normalize_params($params);
    if (!$stmt->execute($params)) {
        $GLOBALS['DB_LAST_ERROR'] = implode(' ', $stmt->errorInfo());
        return false;
    }

    return $stmt;
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

function db_last_error(): string {
    return (string) ($GLOBALS['DB_LAST_ERROR'] ?? '');
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
