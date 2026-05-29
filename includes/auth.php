
<?php
// includes/auth.php - Authentication & Session Management

require_once __DIR__ . '/config.php';

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        $user = Database::fetchOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [strtolower(trim($email))]
        );

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['logged_in']  = true;
            $_SESSION['login_time'] = time();
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) &&
               !empty($_SESSION['user_id']) &&
               (time() - ($_SESSION['login_time'] ?? 0)) < SESSION_LIFETIME;
    }

    public static function requireLogin(): void {
        self::startSession();
        if (!self::isLoggedIn()) {
            if (isAjax()) {
                jsonResponse(['error' => 'Unauthorized', 'redirect' => BASE_URL . '/admin/login'], 401);
            }
            redirect(BASE_URL . '/admin/login');
        }
        // Refresh session
        $_SESSION['login_time'] = time();
    }

    public static function currentUser(): array {
        return [
            'id'    => $_SESSION['user_id'] ?? null,
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['user_role'] ?? '',
        ];
    }

    public static function generateCsrf(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verifyCsrf(string $token): bool {
        return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

Auth::startSession();
