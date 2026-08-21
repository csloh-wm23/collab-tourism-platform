<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}
if ($argc < 4) {
    exit("Usage: php scripts/create_admin.php \"Full Name\" email@example.com \"StrongPassword\"\n");
}

[$script,$name,$email,$password]=$argv;
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8) {
    exit("Use a valid email and password of at least 8 characters.\n");
}

$db=database();
$stmt=$db->prepare("INSERT INTO users (full_name,email,password_hash,role,status) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),password_hash=VALUES(password_hash),role='admin',status='active'");
$stmt->execute([mb_substr($name,0,120),mb_strtolower($email),password_hash($password,PASSWORD_DEFAULT),'admin','active']);
echo "Admin account ready: {$email}\n";
