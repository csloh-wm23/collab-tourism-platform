<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/csv.php';
require_once __DIR__ . '/../config/auth.php';

$checks = 0;
function security_check(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}

// Temporary table: exercise real database constraints without changing accounts.
$db = database();
$db->exec('CREATE TEMPORARY TABLE security_email_test LIKE users');
$insert = $db->prepare('INSERT INTO security_email_test (full_name,email,password_hash) VALUES (?,?,?)');
$insert->execute(['Test', 'test@gmail.com', 'not-a-real-password']);
foreach (['Test@gmail.com', 'test+1@gmail.com', 't.e.s.t@gmail.com', 'test@googlemail.com'] as $alias) {
    security_check(email_identity($alias) === 'test@gmail.com', 'PHP Gmail identity mismatch');
    try {
        $insert->execute(['Test', $alias, 'not-a-real-password']);
        throw new RuntimeException('Database allowed duplicate alias');
    } catch (PDOException $e) {
        security_check($e->getCode() === '23000', 'Expected unique constraint rejection');
    }
}
foreach (['person@example.com', 'per.son@example.com', 'person+1@example.com'] as $address) {
    $insert->execute(['Test', $address, 'not-a-real-password']);
    security_check(email_identity($address) === $address, 'Non-Gmail address changed');
}
try {
    $db->exec("UPDATE security_email_test SET email='t.est+profile@gmail.com' WHERE email='person@example.com'");
    throw new RuntimeException('Profile update bypassed unique constraint');
} catch (PDOException $e) {
    security_check($e->getCode() === '23000', 'Update must reject duplicate inbox');
}
foreach ($db->query('SELECT email,email_identity FROM security_email_test') as $row) {
    security_check(email_identity($row['email']) === $row['email_identity'], 'PHP/database identity mismatch');
}
security_check(!filter_var('test@+1gmail.com', FILTER_VALIDATE_EMAIL), 'Malformed domain accepted');
security_check(!verify_csrf(['unexpected-array']), 'Array CSRF accepted');
security_check(!verify_csrf(null), 'Missing CSRF accepted');
security_check(verify_csrf(csrf_token()), 'Valid CSRF rejected');

foreach (['=1+1', '+SUM(1,2)', '-1+2', '@SUM(1,2)', "\t=1", '  =1', '＝1+1'] as $formula) {
    $stream = fopen('php://temp', 'w+');
    safe_csv_row($stream, [$formula, 'Ordinary text', 42]);
    rewind($stream);
    $row = fgetcsv($stream, 0, ',', '"', '');
    fclose($stream);
    security_check($row === ["'" . $formula, 'Ordinary text', '42'], 'Unsafe CSV output');
}
echo "PASS: {$checks} security checks (temporary table only)\n";
