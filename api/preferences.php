<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';

function preferences_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'tourist' || ($user['status'] ?? '') !== 'active') {
    preferences_reply(['ok'=>false,'message'=>'Active tourist account required.'],403);
}

try {
    $db = database();
    $userId = (int)$user['id'];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $db->prepare(
            "SELECT consent_type, is_granted FROM consent_records WHERE user_id=? AND consent_type IN ('save_translation_history','anonymous_analytics') ORDER BY recorded_at DESC, id DESC"
        );
        $stmt->execute([$userId]);
        $preferences = ['save_history'=>true,'analytics'=>false];
        $seen = [];
        foreach ($stmt->fetchAll() as $row) {
            $type = (string)$row['consent_type'];
            if (isset($seen[$type])) continue;
            $seen[$type] = true;
            if ($type === 'save_translation_history') $preferences['save_history'] = (bool)$row['is_granted'];
            if ($type === 'anonymous_analytics') $preferences['analytics'] = (bool)$row['is_granted'];
        }
        preferences_reply(['ok'=>true,'preferences'=>$preferences]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        preferences_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) preferences_reply(['ok'=>false,'message'=>'Invalid JSON body.'],400);
    if (!verify_csrf($input['csrf'] ?? null)) preferences_reply(['ok'=>false,'message'=>'Invalid request token.'],403);
    if (!array_key_exists('save_history',$input) || !is_bool($input['save_history']) || !array_key_exists('analytics',$input) || !is_bool($input['analytics'])) {
        preferences_reply(['ok'=>false,'message'=>'Both privacy choices must be true or false.'],422);
    }

    $db->beginTransaction();
    $insert = $db->prepare('INSERT INTO consent_records (user_id,consent_type,is_granted,policy_version) VALUES (?,?,?,?)');
    $insert->execute([$userId,'save_translation_history',$input['save_history'] ? 1 : 0,'2026-08']);
    $insert->execute([$userId,'anonymous_analytics',$input['analytics'] ? 1 : 0,'2026-08']);
    $db->commit();
    preferences_reply(['ok'=>true,'message'=>'Privacy choices saved.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Preferences error: ' . $e->getMessage());
    preferences_reply(['ok'=>false,'message'=>'Could not save privacy choices.'],500);
}
