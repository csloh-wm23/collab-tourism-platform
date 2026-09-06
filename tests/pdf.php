<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/admin_pdf.php';

$activity = [];
for ($index = 0; $index < 28; $index++) {
    $activity[] = [
        'created_at' => '2026-09-06 10:' . str_pad((string)$index, 2, '0', STR_PAD_LEFT) . ':00',
        'administrator' => 'System Admin',
        'action' => $index % 2 === 0 ? 'Business registration approved' : 'Business registration reviewed',
        'area' => 'Registrations',
    ];
}

$data = [
    'generated_at' => '2026-09-06T10:30:00+08:00',
    'stats' => ['users' => 42, 'businesses' => 12, 'pending' => 3, 'translations' => 864],
    'users_by_role' => [
        ['role' => 'tourist', 'active' => 25, 'pending' => 0, 'suspended' => 1, 'total' => 26],
        ['role' => 'business', 'active' => 12, 'pending' => 3, 'suspended' => 0, 'total' => 15],
        ['role' => 'admin', 'active' => 1, 'pending' => 0, 'suspended' => 0, 'total' => 1],
    ],
    'businesses_by_status' => [
        ['status' => 'approved', 'total' => 12],
        ['status' => 'pending', 'total' => 3],
    ],
    'pending' => [
        ['name' => 'Kuala Lumpur Food House', 'category' => 'Food and drink', 'email' => 'owner@example.test'],
        ['name' => 'Penang Visitor Centre', 'category' => 'Tourism service', 'email' => 'visitor@example.test'],
    ],
    'recent_activity' => $activity,
];

$pdf = render_admin_pdf($data);
if (!str_starts_with($pdf, '%PDF-1.4') || substr_count($pdf, '/Type /Page ') < 2 || !str_contains($pdf, 'TourLingo')) {
    fwrite(STDERR, "FAILED: invalid administration PDF\n");
    exit(1);
}

$directory = dirname(__DIR__) . '/tmp/pdfs';
if (!is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$path = $directory . '/admin-report-qa.pdf';
file_put_contents($path, $pdf);
echo "PASS: administration PDF generated at {$path}\n";
