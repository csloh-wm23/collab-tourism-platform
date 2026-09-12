<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/admin_pdf.php';
require_once __DIR__ . '/../config/validation.php';
require_once __DIR__ . '/../config/csv.php';

function reply(array $body, int $status = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function admin_dataset(PDO $db): array
{
    $pending = $db
        ->query(
            "SELECT b.id,b.name,b.category,b.address,u.full_name,u.email
         FROM businesses b
         JOIN users u ON u.id=b.owner_user_id
         WHERE b.verification_status='pending'
         ORDER BY b.created_at",
        )
        ->fetchAll();

    $stats = [
        'users' => (int) $db
            ->query("SELECT COUNT(*) FROM users WHERE status='active'")
            ->fetchColumn(),
        'businesses' => (int) $db
            ->query("SELECT COUNT(*) FROM businesses WHERE verification_status='approved'")
            ->fetchColumn(),
        'pending' => (int) $db
            ->query("SELECT COUNT(*) FROM businesses WHERE verification_status='pending'")
            ->fetchColumn(),
        'translations' => (int) $db
            ->query("SELECT COUNT(*) FROM records WHERE record_type='translation'")
            ->fetchColumn(),
    ];

    $usersByRole = $db
        ->query(
            "SELECT role, COUNT(*) AS total,
                SUM(status='active') AS active,
                SUM(status='pending') AS pending,
                SUM(status='suspended') AS suspended
         FROM users
         GROUP BY role
         ORDER BY FIELD(role,'tourist','business','editor','admin')",
        )
        ->fetchAll();

    $businessesByStatus = $db
        ->query(
            "SELECT verification_status AS status, COUNT(*) AS total
         FROM businesses
         GROUP BY verification_status
         ORDER BY FIELD(verification_status,'approved','pending','rejected')",
        )
        ->fetchAll();

    $recentActivity = $db
        ->query(
            "SELECT a.created_at, COALESCE(u.full_name,'System') AS administrator, a.action, a.area
         FROM audit_logs a
         LEFT JOIN users u ON u.id=a.user_id
         ORDER BY a.created_at DESC
         LIMIT 20",
        )
        ->fetchAll();

    return [
        'pending' => $pending,
        'stats' => $stats,
        'users_by_role' => $usersByRole,
        'businesses_by_status' => $businessesByStatus,
        'recent_activity' => $recentActivity,
        'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format(DATE_ATOM),
    ];
}

function export_admin_csv(array $data): never
{
    $filename = 'tourlingo-administration-report-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        reply(['ok' => false, 'message' => 'Could not create the report.'], 500);
    }

    fwrite($output, "\xEF\xBB\xBF");
    safe_csv_row($output, ['TourLingo administration report']);
    safe_csv_row($output, ['Generated', $data['generated_at']]);
    safe_csv_row($output, []);
    safe_csv_row($output, ['Platform summary']);
    safe_csv_row($output, ['Metric', 'Value']);
    safe_csv_row($output, ['Active users', $data['stats']['users']]);
    safe_csv_row($output, ['Approved businesses', $data['stats']['businesses']]);
    safe_csv_row($output, ['Pending reviews', $data['stats']['pending']]);
    safe_csv_row($output, ['Saved translations', $data['stats']['translations']]);

    safe_csv_row($output, []);
    safe_csv_row($output, ['User accounts by role']);
    safe_csv_row($output, ['Role', 'Active', 'Pending', 'Suspended', 'Total']);
    foreach ($data['users_by_role'] as $row) {
        safe_csv_row($output, [
            ucfirst((string) $row['role']),
            $row['active'],
            $row['pending'],
            $row['suspended'],
            $row['total'],
        ]);
    }

    safe_csv_row($output, []);
    safe_csv_row($output, ['Businesses by review status']);
    safe_csv_row($output, ['Status', 'Total']);
    foreach ($data['businesses_by_status'] as $row) {
        safe_csv_row($output, [ucfirst((string) $row['status']), $row['total']]);
    }

    safe_csv_row($output, []);
    safe_csv_row($output, ['Pending business registrations']);
    safe_csv_row($output, ['Business', 'Category', 'Owner email']);
    foreach ($data['pending'] as $row) {
        safe_csv_row($output, [$row['name'], $row['category'], $row['email']]);
    }

    safe_csv_row($output, []);
    safe_csv_row($output, ['Recent administrative activity']);
    safe_csv_row($output, ['Date (MYT)', 'Staff member', 'Action', 'Area']);
    foreach ($data['recent_activity'] as $row) {
        safe_csv_row($output, [$row['created_at'], $row['administrator'], $row['action'], $row['area']]);
    }
    fclose($output);
    exit();
}

function export_admin_pdf(array $data): never
{
    $filename = 'tourlingo-administration-report-' . date('Y-m-d') . '.pdf';
    $pdf = render_admin_pdf($data);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit();
}

$admin = current_user();
if (!$admin || ($admin['role'] ?? '') !== 'admin' || ($admin['status'] ?? '') !== 'active') {
    reply(['ok' => false, 'message' => 'Active administrator access required.'], 403);
}

try {
    $db = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $data = admin_dataset($db);
        $format = (string) ($_GET['format'] ?? '');
        if ($format === 'csv') {
            export_admin_csv($data);
        }
        if ($format === 'pdf') {
            export_admin_pdf($data);
        }
        reply(['ok' => true] + $data);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || !verify_csrf($input['csrf'] ?? null)) {
        reply(['ok' => false, 'message' => 'Invalid request token.'], 403);
    }
    $id = (int) ($input['business_id'] ?? 0);
    $decision = (string) ($input['decision'] ?? '');
    if (!$id || !in_array($decision, ['approve', 'reject'], true)) {
        reply(['ok' => false, 'message' => 'Invalid decision.'], 422);
    }

    // Lock the application during review so concurrent decisions cannot overwrite each other.
    $reason = clean_text($input['reason'] ?? '', 1000);
    if ($decision === 'reject' && $reason === '') reply(['ok' => false, 'message' => 'Give the applicant a reason for rejection.'], 422);
    // Business status, owner status and the audit record are saved as one transaction.
    $db->beginTransaction();
    $stmt = $db->prepare(
        'SELECT owner_user_id, verification_status FROM businesses WHERE id=? FOR UPDATE',
    );
    $stmt->execute([$id]);
    $registration = $stmt->fetch();
    $owner = (int) ($registration['owner_user_id'] ?? 0);
    if (!$owner) {
        $db->rollBack();
        reply(['ok' => false, 'message' => 'Business not found.'], 404);
    }
    if (($registration['verification_status'] ?? '') !== 'pending') {
        $db->rollBack();
        reply(['ok' => false, 'message' => 'Only pending registrations can be reviewed.'], 409);
    }

    $businessStatus = $decision === 'approve' ? 'approved' : 'rejected';
    $userStatus = $decision === 'approve' ? 'active' : 'pending';
    $db->prepare('UPDATE businesses SET verification_status=? WHERE id=?')->execute([
        $businessStatus,
        $id,
    ]);
    $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$userStatus, $owner]);
    $db->prepare('INSERT INTO audit_logs (user_id,action,area,details) VALUES (?,?,?,?)')->execute([
        (int) $admin['id'],
        'Business registration ' . $decision,
        'Registrations',
        json_encode(['business_id' => $id, 'decision' => $decision, 'reason' => $reason]),
    ]);
    $db->commit();
    reply(['ok' => true]);
} catch (InvalidArgumentException $exception) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    reply(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Administration error: ' . $exception->getMessage());
    reply(['ok' => false, 'message' => 'Server error.'], 500);
}
