<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation.php';

function reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$user = current_user();
if (!$user) {
    reply(['ok' => false, 'guest' => true, 'message' => 'Log in to use server history.'], 401);
}
if (!in_array((string) ($user['role'] ?? ''), ['tourist', 'business'], true)) {
    reply(
        [
            'ok' => false,
            'message' => 'Personal history is available to tourist and business accounts only.',
        ],
        403,
    );
}
if (($user['status'] ?? '') === 'suspended') {
    reply(['ok' => false, 'message' => 'This account is suspended.'], 403);
}

try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $userId = (int) $user['id'];

    if ($method === 'GET') {
        $type = trim((string) ($_GET['type'] ?? ''));
        $params = [$userId];
        $sql =
            'SELECT id, record_type, title, content, source_language, target_language, scenario, confidence, is_favorite, metadata, created_at FROM records WHERE user_id = ?';
        if ($type !== '') {
            $allowed = ['translation', 'phrase'];
            if (!in_array($type, $allowed, true)) {
                reply(['ok' => false, 'message' => 'Invalid type.'], 422);
            }
            $sql .= ' AND record_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 100';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        reply(['ok' => true, 'records' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
        }
        if (!verify_csrf($input['csrf'] ?? null)) {
            reply(['ok' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $type = trim((string) ($input['record_type'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $allowed = ['translation', 'phrase'];
        if (!in_array($type, $allowed, true) || $title === '' || $content === '') {
            reply(
                ['ok' => false, 'message' => 'record_type, title and content are required.'],
                422,
            );
        }

        if ($type === 'translation') {
            $preference = $db->prepare(
                "SELECT is_granted FROM consent_records WHERE user_id=? AND consent_type='save_translation_history' ORDER BY recorded_at DESC, id DESC LIMIT 1",
            );
            $preference->execute([$userId]);
            $savedPreference = $preference->fetchColumn();
            if ($savedPreference !== false && (int) $savedPreference !== 1) {
                reply(
                    [
                        'ok' => false,
                        'message' => 'Translation history is disabled in your privacy choices.',
                    ],
                    403,
                );
            }
        }

        // Keep the complete original separately from the short list title.
        $recordMetadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        $recordMetadata['source_text'] = clean_text($title, 500, true);
        $metadata = json_encode(
            $recordMetadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $from = isset($input['source_language'])
            ? clean_language($input['source_language'], true)
            : null;
        $to = isset($input['target_language']) ? clean_language($input['target_language']) : null;
        $scenario = isset($input['scenario']) ? clean_scenario($input['scenario']) : null;
        $confidence = clean_confidence($input['confidence'] ?? null);
        $stmt = $db->prepare(
            'INSERT INTO records (user_id, record_type, title, content, source_language, target_language, scenario, confidence, is_favorite, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $userId,
            $type,
            mb_substr($title, 0, 150),
            mb_substr($content, 0, 5000),
            $from,
            $to,
            $scenario,
            $confidence,
            !empty($input['is_favorite']) ? 1 : 0,
            $metadata,
        ]);
        reply(['ok' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input) || !verify_csrf($input['csrf'] ?? null)) {
            reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
        }
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            reply(['ok' => false, 'message' => 'Valid id required.'], 422);
        }
        $stmt = $db->prepare('UPDATE records SET is_favorite=? WHERE id=? AND user_id=?');
        $stmt->execute([!empty($input['is_favorite']) ? 1 : 0, $id, $userId]);
        reply(['ok' => true, 'updated' => $stmt->rowCount()]);
    }

    if ($method === 'DELETE') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$id) {
            reply(['ok' => false, 'message' => 'Valid id required.'], 422);
        }
        if (!verify_csrf($csrf)) {
            reply(['ok' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $stmt = $db->prepare('DELETE FROM records WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        reply(['ok' => true, 'deleted' => $stmt->rowCount()]);
    }

    reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
} catch (InvalidArgumentException $e) {
    reply(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log($e->getMessage());
    reply(['ok' => false, 'offline' => true, 'message' => 'Database unavailable.'], 503);
}
