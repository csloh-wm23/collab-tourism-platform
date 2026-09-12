<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation.php';
require_once __DIR__ . '/../config/email.php';

function profile_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$user = current_user();
if (!$user) {
    profile_reply(['ok' => false, 'message' => 'Log in to manage your profile.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    profile_reply([
        'ok' => true,
        'profile' => [
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'preferred_language' => $user['preferred_language'],
            'profile_image' => $user['profile_image'] ?? null,
            'role' => $user['role'],
            'status' => $user['status'],
        ],
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    profile_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

// Pictures arrive as multipart form data, while account/password changes use JSON.
// Both paths verify CSRF and use the authenticated user's ID, not a submitted owner ID.
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_starts_with($contentType, 'multipart/form-data')) {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        profile_reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
    }
    if (($_POST['action'] ?? '') !== 'upload_picture') {
        profile_reply(['ok' => false, 'message' => 'Unknown profile action.'], 422);
    }

    $picture = $_FILES['profile_picture'] ?? null;
    $uploadError = is_array($picture)
        ? (int) ($picture['error'] ?? UPLOAD_ERR_NO_FILE)
        : UPLOAD_ERR_NO_FILE;
    if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        profile_reply(
            ['ok' => false, 'message' => 'Profile pictures must be 2 MB or smaller.'],
            422,
        );
    }
    if (!is_array($picture) || $uploadError !== UPLOAD_ERR_OK) {
        profile_reply(['ok' => false, 'message' => 'Choose a profile picture to upload.'], 422);
    }
    $size = (int) ($picture['size'] ?? 0);
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        profile_reply(
            ['ok' => false, 'message' => 'Profile pictures must be 2 MB or smaller.'],
            422,
        );
    }

    // Validate the actual file type and dimensions, not just the filename extension.
    $temporaryPath = (string) ($picture['tmp_name'] ?? '');
    $mimeType = $temporaryPath !== '' ? (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) : false;
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        profile_reply(['ok' => false, 'message' => 'Use a JPG, PNG or WebP image.'], 422);
    }
    $dimensions = @getimagesize($temporaryPath);
    if (
        !is_array($dimensions) ||
        (int) $dimensions[0] < 1 ||
        (int) $dimensions[1] < 1 ||
        (int) $dimensions[0] > 6000 ||
        (int) $dimensions[1] > 6000
    ) {
        profile_reply(
            ['ok' => false, 'message' => 'Choose a valid image no larger than 6000 × 6000 pixels.'],
            422,
        );
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/profile';
    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true) &&
        !is_dir($uploadDirectory)
    ) {
        profile_reply(
            ['ok' => false, 'message' => 'Could not prepare profile-picture storage.'],
            500,
        );
    }

    $userId = (int) $user['id'];
    // Random filenames avoid collisions; the database stores only the relative image path.
    $filename = 'user-' . $userId . '-' . bin2hex(random_bytes(10)) . '.' . $extensions[$mimeType];
    $destination = $uploadDirectory . '/' . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        profile_reply(['ok' => false, 'message' => 'Could not save the profile picture.'], 500);
    }

    $relativePath = 'uploads/profile/' . $filename;
    try {
        $db = database();
        $oldPath = (string) ($user['profile_image'] ?? '');
        $db->prepare('UPDATE users SET profile_image=? WHERE id=?')->execute([
            $relativePath,
            $userId,
        ]);
        refresh_session_user($db, $userId);

        if (str_starts_with($oldPath, 'uploads/profile/')) {
            $oldAbsolutePath = dirname(__DIR__) . '/' . $oldPath;
            $resolvedOldPath = realpath($oldAbsolutePath);
            $resolvedDirectory = realpath($uploadDirectory);
            if (
                $resolvedOldPath !== false &&
                $resolvedDirectory !== false &&
                dirname($resolvedOldPath) === $resolvedDirectory &&
                is_file($resolvedOldPath)
            ) {
                unlink($resolvedOldPath);
            }
        }
    } catch (Throwable $exception) {
        if (is_file($destination)) {
            unlink($destination);
        }
        error_log('Profile picture update error: ' . $exception->getMessage());
        profile_reply(['ok' => false, 'message' => 'Could not update the profile picture.'], 500);
    }

    profile_reply([
        'ok' => true,
        'message' => 'Profile picture updated.',
        'profile_image' => $relativePath,
    ]);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    profile_reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
}
if (!verify_csrf($input['csrf'] ?? null)) {
    profile_reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
}

$db = database();
$userId = (int) $user['id'];
$action = (string) ($input['action'] ?? '');

try {
    if ($action === 'update_account') {
        $fullName = clean_text($input['full_name'] ?? '', 120, true);
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $language = clean_language($input['preferred_language'] ?? 'en');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            profile_reply(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
        }

        $duplicate = $db->prepare('SELECT id FROM users WHERE email_identity=? AND id<>? LIMIT 1');
        $duplicate->execute([email_identity($email), $userId]);
        if ($duplicate->fetchColumn()) {
            profile_reply(
                ['ok' => false, 'message' => 'That email address is already registered.'],
                409,
            );
        }

        $db->prepare(
            'UPDATE users SET full_name=?,email=?,preferred_language=? WHERE id=?',
        )->execute([$fullName, $email, $language, $userId]);
        refresh_session_user($db, $userId);
        profile_reply([
            'ok' => true,
            'message' => 'Account details saved.',
            'profile' => [
                'full_name' => $fullName,
                'email' => $email,
                'preferred_language' => $language,
                'profile_image' => $user['profile_image'] ?? null,
            ],
        ]);
    }

    // Verify the existing password before storing a new password hash.
    if ($action === 'change_password') {
        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmation = (string) ($input['confirm_password'] ?? '');

        if ($currentPassword === '') {
            profile_reply(['ok' => false, 'message' => 'Enter your current password.'], 422);
        }
        if (strlen($newPassword) < 8) {
            profile_reply(
                ['ok' => false, 'message' => 'New password must be at least 8 characters.'],
                422,
            );
        }
        if ($newPassword !== $confirmation) {
            profile_reply(['ok' => false, 'message' => 'New passwords do not match.'], 422);
        }

        $statement = $db->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $statement->execute([$userId]);
        $passwordHash = (string) $statement->fetchColumn();
        if ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            profile_reply(['ok' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId,
        ]);
        session_regenerate_id(true);
        profile_reply(['ok' => true, 'message' => 'Password updated.']);
    }

    profile_reply(['ok' => false, 'message' => 'Unknown profile action.'], 422);
} catch (InvalidArgumentException $exception) {
    profile_reply(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        profile_reply(['ok' => false, 'message' => 'That email address is already registered.'], 409);
    }
    error_log('Profile update error: ' . $exception->getMessage());
    profile_reply(['ok' => false, 'message' => 'Could not update your profile.'], 500);
}
