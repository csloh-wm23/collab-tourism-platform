<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation.php';

function profile_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = current_user();
if (!$user) {
    profile_reply(['ok' => false, 'message' => 'Log in to manage your profile.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    profile_reply(['ok' => true, 'profile' => [
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'preferred_language' => $user['preferred_language'],
        'role' => $user['role'],
        'status' => $user['status'],
    ]]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    profile_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    profile_reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
}
if (!verify_csrf($input['csrf'] ?? null)) {
    profile_reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
}

$db = database();
$userId = (int)$user['id'];
$action = (string)($input['action'] ?? '');

try {
    if ($action === 'update_account') {
        $fullName = clean_text($input['full_name'] ?? '', 120, true);
        $email = mb_strtolower(trim((string)($input['email'] ?? '')));
        $language = clean_language($input['preferred_language'] ?? 'en');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            profile_reply(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
        }

        $duplicate = $db->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
        $duplicate->execute([$email, $userId]);
        if ($duplicate->fetchColumn()) {
            profile_reply(['ok' => false, 'message' => 'That email address is already registered.'], 409);
        }

        $db->prepare('UPDATE users SET full_name=?,email=?,preferred_language=? WHERE id=?')
            ->execute([$fullName, $email, $language, $userId]);
        refresh_session_user($db, $userId);
        profile_reply(['ok' => true, 'message' => 'Account details saved.', 'profile' => [
            'full_name' => $fullName,
            'email' => $email,
            'preferred_language' => $language,
        ]]);
    }

    if ($action === 'change_password') {
        $currentPassword = (string)($input['current_password'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');
        $confirmation = (string)($input['confirm_password'] ?? '');

        if ($currentPassword === '') {
            profile_reply(['ok' => false, 'message' => 'Enter your current password.'], 422);
        }
        if (strlen($newPassword) < 8) {
            profile_reply(['ok' => false, 'message' => 'New password must be at least 8 characters.'], 422);
        }
        if ($newPassword !== $confirmation) {
            profile_reply(['ok' => false, 'message' => 'New passwords do not match.'], 422);
        }

        $statement = $db->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $statement->execute([$userId]);
        $passwordHash = (string)$statement->fetchColumn();
        if ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            profile_reply(['ok' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        session_regenerate_id(true);
        profile_reply(['ok' => true, 'message' => 'Password updated.']);
    }

    profile_reply(['ok' => false, 'message' => 'Unknown profile action.'], 422);
} catch (InvalidArgumentException $exception) {
    profile_reply(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Profile update error: ' . $exception->getMessage());
    profile_reply(['ok' => false, 'message' => 'Could not update your profile.'], 500);
}
