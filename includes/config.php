<?php
// includes/config.php - Main configuration file

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'BookFlow');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://yourdomain.com/appointment-system');
define('APP_PATH', __DIR__ . '/..');

define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', '_bookflow_csrf');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed']));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('DB query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    public static function update(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }
}

// Helper functions
function getSetting(string $key, string $default = ''): string {
    $row = Database::fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $row ? ($row['setting_value'] ?? $default) : $default;
}

function setSetting(string $key, string $value): void {
    try {
        // Try upsert first (requires UNIQUE key on setting_key)
        Database::update(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $value, $value]
        );
    } catch (PDOException $e) {
        // Fallback: try plain UPDATE then INSERT
        try {
            $updated = Database::update(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
            if ($updated === 0) {
                Database::insert(
                    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
                    [$key, $value]
                );
            }
        } catch (PDOException $e2) {
            error_log('setSetting failed for key=' . $key . ': ' . $e2->getMessage());
        }
    }
}

function generateRef(): string {
    return 'BF-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
