<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function review_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
$user = current_user();
if (!$user || !in_array($user['role'], ['editor', 'admin'], true) || $user['status'] !== 'active') {
    review_reply(['ok' => false, 'message' => 'Active editor or administrator access required.'], 403);
}
try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $before = max(0, (int) ($_GET['before'] ?? 0));
        $stmt = $db->prepare('SELECT id,source_text,translated_text,source_language,target_language,scenario,issue_type,notes,status,created_at FROM translation_reports WHERE (?=0 OR id<?) ORDER BY id DESC LIMIT 50');
        $stmt->execute([$before, $before]);
        $reports = $stmt->fetchAll();
        $history = $db->prepare("SELECT a.action,a.details,a.created_at,u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.area='Translation review' AND JSON_UNQUOTE(JSON_EXTRACT(a.details,'$.report_id'))=? ORDER BY a.id DESC LIMIT 20");
        foreach ($reports as &$report) {
            $history->execute([(string) $report['id']]);
            $report['reviews'] = $history->fetchAll();
        }
        unset($report);
        review_reply(['ok' => true, 'reports' => $reports]);
    }
    if ($method !== 'POST') review_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || !verify_csrf($input['csrf'] ?? null)) review_reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
    $id = (int) ($input['id'] ?? 0);
    $status = $input['status'] ?? '';
    if (!in_array($status, ['open', 'reviewing', 'resolved'], true)) throw new InvalidArgumentException('Invalid review status.');
    $note = clean_text($input['note'] ?? '', 1000, true);
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT status FROM translation_reports WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);
    $previous = $stmt->fetchColumn();
    if ($previous === false) { $db->rollBack(); review_reply(['ok' => false, 'message' => 'Report not found.'], 404); }
    if ($previous !== ($input['previous_status'] ?? '')) { $db->rollBack(); review_reply(['ok' => false, 'message' => 'Another reviewer changed this report. Refresh before saving.'], 409); }
    $db->prepare('UPDATE translation_reports SET status=? WHERE id=?')->execute([$status, $id]);
    // Audit notes document human decisions; no provider model is modified.
    $db->prepare('INSERT INTO audit_logs(user_id,action,area,details) VALUES(?,?,?,?)')->execute([
        (int) $user['id'], 'Translation report ' . $status, 'Translation review',
        json_encode(['report_id' => $id, 'previous_status' => $previous, 'status' => $status, 'note' => $note], JSON_UNESCAPED_UNICODE)
    ]);
    $db->commit();
    review_reply(['ok' => true]);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    review_reply(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Translation review: ' . $e->getMessage());
    review_reply(['ok' => false, 'message' => 'Could not load or save the review.'], 503);
}
