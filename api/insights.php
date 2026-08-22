<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';

function insights_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    insights_reply(['ok'=>false,'message'=>'Method not allowed.'],405);
}

$user = current_user();
if (!$user || !in_array((string)($user['role'] ?? ''), ['editor','admin'], true) || ($user['status'] ?? '') !== 'active') {
    insights_reply(['ok'=>false,'message'=>'Active editor or administrator access required.'],403);
}

try {
    $db = database();
    $recordPhrases = (int)$db->query("SELECT COUNT(*) FROM records WHERE record_type='phrase'")->fetchColumn();
    $businessPhrases = (int)$db->query('SELECT COUNT(*) FROM business_phrases')->fetchColumn();
    insights_reply([
        'ok'=>true,
        'stats'=>[
            'translations'=>(int)$db->query("SELECT COUNT(*) FROM records WHERE record_type='translation'")->fetchColumn(),
            'phrases'=>$recordPhrases + $businessPhrases,
            'businesses'=>(int)$db->query("SELECT COUNT(*) FROM users WHERE role='business'")->fetchColumn(),
            'pending_businesses'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='pending'")->fetchColumn(),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Insights error: ' . $e->getMessage());
    insights_reply(['ok'=>false,'message'=>'Insights are temporarily unavailable.'],500);
}
