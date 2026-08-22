<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

/**
 * Load the latest database user once per request. Role and status stored in
 * the session are never used as the authorization source.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $current = null;

    if ($loaded) {
        return $current;
    }
    $loaded = true;

    $sessionUser = $_SESSION['user'] ?? null;
    $userId = is_array($sessionUser) ? (int)($sessionUser['id'] ?? 0) : 0;
    if ($userId < 1) {
        return null;
    }

    try {
        $stmt = database()->prepare(
            'SELECT id, full_name, email, role, status, preferred_language FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            unset($_SESSION['user']);
            return null;
        }

        $_SESSION['user'] = $user;
        $current = $user;
        return $current;
    } catch (Throwable $e) {
        // Fail closed instead of authorizing from stale session data.
        error_log('Unable to refresh authenticated user: ' . $e->getMessage());
        return null;
    }
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array((string)$user['role'], $roles, true);
}

function is_active_user(): bool
{
    $user = current_user();
    return $user !== null && ($user['status'] ?? '') === 'active';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function refresh_session_user(PDO $db, int $userId): void
{
    $stmt = $db->prepare('SELECT id, full_name, email, role, status, preferred_language FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user'] = $user;
    }
}
