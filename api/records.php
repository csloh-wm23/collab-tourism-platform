<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';

function reply(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $type = trim((string) ($_GET['type'] ?? ''));
        if ($type !== '') {
            $statement = $db->prepare('SELECT id, record_type, title, content, metadata, created_at FROM records WHERE record_type = ? ORDER BY created_at DESC LIMIT 100');
            $statement->execute([$type]);
        } else {
            $statement = $db->query('SELECT id, record_type, title, content, metadata, created_at FROM records ORDER BY created_at DESC LIMIT 100');
        }
        reply(['ok' => true, 'records' => $statement->fetchAll()]);
    }

    if ($method === 'POST') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
        }

        $type = trim((string) ($input['record_type'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $allowedTypes = ['translation', 'phrase', 'report', 'business', 'emergency', 'consent'];

        if (!in_array($type, $allowedTypes, true) || $title === '' || $content === '') {
            reply(['ok' => false, 'message' => 'record_type, title and content are required.'], 422);
        }

        $metadata = json_encode($input['metadata'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $safeTitle = function_exists('mb_substr') ? mb_substr($title, 0, 150) : substr($title, 0, 150);
        $safeContent = function_exists('mb_substr') ? mb_substr($content, 0, 5000) : substr($content, 0, 5000);
        $statement = $db->prepare('INSERT INTO records (record_type, title, content, metadata) VALUES (?, ?, ?, ?)');
        $statement->execute([$type, $safeTitle, $safeContent, $metadata]);
        reply(['ok' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($method === 'DELETE') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            reply(['ok' => false, 'message' => 'A valid id is required.'], 422);
        }
        $statement = $db->prepare('DELETE FROM records WHERE id = ?');
        $statement->execute([$id]);
        reply(['ok' => true]);
    }

    reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
} catch (PDOException $error) {
    error_log($error->getMessage());
    reply(['ok' => false, 'offline' => true, 'message' => 'Database is unavailable. The browser will use local storage.'], 503);
} catch (Throwable $error) {
    error_log($error->getMessage());
    reply(['ok' => false, 'message' => 'Unexpected server error.'], 500);
}
