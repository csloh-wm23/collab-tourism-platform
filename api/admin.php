<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function reply(array $b,int $s=200): never { http_response_code($s); echo json_encode($b,JSON_UNESCAPED_UNICODE); exit; }
if (!has_role('admin')) reply(['ok'=>false,'message'=>'Admin access required.'],403);

try {
    $db=database();

    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $pending=$db->query("SELECT b.id,b.name,b.category,b.address,u.full_name,u.email FROM businesses b JOIN users u ON u.id=b.owner_user_id WHERE b.verification_status='pending' ORDER BY b.created_at")->fetchAll();
        $stats=[
            'users'=>(int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
            'businesses'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='approved'")->fetchColumn(),
            'pending'=>(int)$db->query("SELECT COUNT(*) FROM businesses WHERE verification_status='pending'")->fetchColumn(),
            'translations'=>(int)$db->query("SELECT COUNT(*) FROM records WHERE record_type='translation'")->fetchColumn(),
        ];
        reply(['ok'=>true,'pending'=>$pending,'stats'=>$stats]);
    }

    $input=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($input)||!verify_csrf($input['csrf']??null)) reply(['ok'=>false,'message'=>'Invalid request token.'],403);
    $id=(int)($input['business_id']??0);
    $decision=(string)($input['decision']??'');
    if (!$id||!in_array($decision,['approve','reject'],true)) reply(['ok'=>false,'message'=>'Invalid decision.'],422);

    $db->beginTransaction();
    $stmt=$db->prepare('SELECT owner_user_id FROM businesses WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);
    $owner=(int)($stmt->fetchColumn()?:0);
    if (!$owner) { $db->rollBack(); reply(['ok'=>false,'message'=>'Business not found.'],404); }

    $businessStatus=$decision==='approve'?'approved':'rejected';
    $userStatus=$decision==='approve'?'active':'suspended';
    $db->prepare('UPDATE businesses SET verification_status=? WHERE id=?')->execute([$businessStatus,$id]);
    $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$userStatus,$owner]);
    $db->prepare('INSERT INTO audit_logs (user_id,action,area,details) VALUES (?,?,?,?)')
        ->execute([(int)current_user()['id'],'Business registration '.$decision,'Registrations',json_encode(['business_id'=>$id])]);
    $db->commit();
    reply(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($db)&&$db->inTransaction()) $db->rollBack();
    error_log($e->getMessage());
    reply(['ok'=>false,'message'=>'Server error.'],500);
}
