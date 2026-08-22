<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function reply(array $body, int $status=200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'business') {
        reply(['ok'=>false,'message'=>'Business account access required.'],403);
    }

    if (($user['status'] ?? '') === 'suspended') {
        reply(['ok'=>false,'message'=>'This business account is suspended.'],403);
    }

    $db = database();
    $stmt = $db->prepare('SELECT * FROM businesses WHERE owner_user_id=? LIMIT 1');
    $stmt->execute([(int)$user['id']]);
    $business = $stmt->fetch();
    if (!$business) reply(['ok'=>false,'message'=>'Business profile not found.'],404);
    if (($user['status'] ?? '') !== 'active' || ($business['verification_status'] ?? '') !== 'approved') {
        $message = ($business['verification_status'] ?? '') === 'rejected'
            ? 'This business registration was rejected.'
            : 'Pending administrator approval.';
        reply(['ok'=>false,'message'=>$message],403);
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $p = $db->prepare('SELECT id, source_language, target_language, source_text, translated_text, category, is_published FROM business_phrases WHERE business_id=? ORDER BY created_at DESC');
        $p->execute([(int)$business['id']]);
        reply(['ok'=>true,'business'=>$business,'phrases'=>$p->fetchAll()]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        reply(['ok'=>false,'message'=>'Method not allowed.'],405);
    }

    $input=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($input) || !verify_csrf($input['csrf']??null)) reply(['ok'=>false,'message'=>'Invalid request token.'],403);
    $action=(string)($input['action']??'');

    if ($action==='save_profile') {
        $name=trim((string)($input['name']??''));
        $category=trim((string)($input['category']??''));
        $address=trim((string)($input['address']??''));
        if ($name===''||$category===''||$address==='') reply(['ok'=>false,'message'=>'Name, category and address are required.'],422);
        $db->prepare('UPDATE businesses SET name=?,category=?,address=? WHERE id=?')
            ->execute([mb_substr($name,0,160),mb_substr($category,0,80),mb_substr($address,0,500),(int)$business['id']]);
        reply(['ok'=>true]);
    }

    if ($action==='add_phrase') {
        $source=trim((string)($input['source_text']??''));
        $translated=trim((string)($input['translated_text']??''));
        $target=(string)($input['target_language']??'ms');
        $category=trim((string)($input['category']??'General'));
        if ($source===''||$translated==='') reply(['ok'=>false,'message'=>'Both phrase fields are required.'],422);
        if (!in_array($target,['en','ms','zh','ta'],true)) reply(['ok'=>false,'message'=>'Unsupported target language.'],422);
        $db->prepare('INSERT INTO business_phrases (business_id,source_language,target_language,source_text,translated_text,category) VALUES (?,?,?,?,?,?)')
            ->execute([(int)$business['id'],'en',$target,mb_substr($source,0,500),mb_substr($translated,0,500),mb_substr($category,0,80)]);
        reply(['ok'=>true,'id'=>(int)$db->lastInsertId()],201);
    }

    reply(['ok'=>false,'message'=>'Unknown action.'],422);
} catch (Throwable $e) {
    error_log($e->getMessage());
    reply(['ok'=>false,'message'=>'Database error.'],500);
}
