<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function reply(array $b,int $s=200): never { http_response_code($s); echo json_encode($b,JSON_UNESCAPED_UNICODE); exit; }
$admin = current_user();
if (!$admin || ($admin['role'] ?? '') !== 'admin' || ($admin['status'] ?? '') !== 'active') {
    reply(['ok'=>false,'message'=>'Active administrator access required.'],403);
}

try {
    $db=database();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $pending=$db->query("SELECT b.id,b.name,b.category,b.address,u.full_name,u.email FROM businesses b JOIN users u ON u.id=b.owner_user_id WHERE b.verification_status='pending' ORDER BY b.created_at")->fetchAll();
        $stats=[
            'users'=>(int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
            'businesses'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='approved'")->fetchColumn(),
            'pending'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='pending'")->fetchColumn(),
            'translations'=>(int)$db->query("SELECT COUNT(*) FROM records WHERE record_type='translation'")->fetchColumn(),
        ];
        reply(['ok'=>true,'pending'=>$pending,'stats'=>$stats]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        reply(['ok'=>false,'message'=>'Method not allowed.'],405);
    }

    $input=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($input)||!verify_csrf($input['csrf']??null)) reply(['ok'=>false,'message'=>'Invalid request token.'],403);
    $id=(int)($input['business_id']??0);
    $decision=(string)($input['decision']??'');
    if (!$id||!in_array($decision,['approve','reject'],true)) reply(['ok'=>false,'message'=>'Invalid decision.'],422);

    $db->beginTransaction();
    $stmt=$db->prepare('SELECT owner_user_id, verification_status FROM businesses WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);
    $registration=$stmt->fetch();
    $owner=(int)($registration['owner_user_id']??0);
    if (!$owner) { $db->rollBack(); reply(['ok'=>false,'message'=>'Business not found.'],404); }
    if (($registration['verification_status'] ?? '') !== 'pending') {
        $db->rollBack();
        reply(['ok'=>false,'message'=>'Only pending registrations can be reviewed.'],409);
    }

    $businessStatus=$decision==='approve'?'approved':'rejected';
    // Rejection is an application decision, not a malicious-account suspension.
    $userStatus=$decision==='approve'?'active':'pending';
    $db->prepare('UPDATE businesses SET verification_status=? WHERE id=?')->execute([$businessStatus,$id]);
    $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$userStatus,$owner]);
    $db->prepare('INSERT INTO audit_logs (user_id,action,area,details) VALUES (?,?,?,?)')
        ->execute([(int)$admin['id'],'Business registration '.$decision,'Registrations',json_encode(['business_id'=>$id,'decision'=>$decision])]);
    $db->commit();
    reply(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($db)&&$db->inTransaction()) $db->rollBack();
    error_log($e->getMessage());
    reply(['ok'=>false,'message'=>'Server error.'],500);
}
