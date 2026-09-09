<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$password = 'TourLingoDemo#2026';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$accounts = [
    ['Aina Rahman', 'test.cafe@gmail.com', 'Rasa Malaysia Cafe', 'Food & drink', 'Jalan Alor, Kuala Lumpur', 'rasa-malaysia-cafe-demo'],
    ['Daniel Lee', 'test.hotel@gmail.com', 'City Lights Boutique Hotel', 'Accommodation', 'Bukit Bintang, Kuala Lumpur', 'city-lights-hotel-demo'],
    ['Siti Hana', 'test.transport@gmail.com', 'KL Easy Ride', 'Transportation', 'KL Sentral, Kuala Lumpur', 'kl-easy-ride-demo'],
    ['Arjun Kumar', 'test.touragency@gmail.com', 'Discover Malaysia Tours', 'Tour operator', 'Petaling Street, Kuala Lumpur', 'discover-malaysia-tours-demo'],
    ['Mei Ling Tan', 'test.shop@gmail.com', 'Local Treasures Market', 'Shopping', 'Central Market, Kuala Lumpur', 'local-treasures-market-demo'],
];

$db = database();
$db->beginTransaction();

try {
    $saveUser = $db->prepare(
        "INSERT INTO users (full_name,email,password_hash,role,status,preferred_language,failed_login_attempts,locked_until)
         VALUES (?,?,?,'business','pending','en',0,NULL)
         ON DUPLICATE KEY UPDATE
           full_name=VALUES(full_name), password_hash=VALUES(password_hash), role='business',
           status='pending', failed_login_attempts=0, locked_until=NULL"
    );
    $findUser = $db->prepare('SELECT id FROM users WHERE email=?');
    $findBusiness = $db->prepare('SELECT id FROM businesses WHERE owner_user_id=? LIMIT 1');
    $updateBusiness = $db->prepare(
        "UPDATE businesses SET name=?,category=?,address=?,verification_status='pending',qr_slug=?,is_public=0 WHERE id=?"
    );
    $createBusiness = $db->prepare(
        "INSERT INTO businesses (owner_user_id,name,category,address,verification_status,qr_slug,is_public)
         VALUES (?,?,?,?,'pending',?,0)"
    );

    foreach ($accounts as [$owner, $email, $name, $category, $address, $slug]) {
        $saveUser->execute([$owner, $email, $passwordHash]);
        $findUser->execute([$email]);
        $userId = (int) $findUser->fetchColumn();
        $findBusiness->execute([$userId]);
        $businessId = (int) $findBusiness->fetchColumn();

        if ($businessId > 0) {
            $updateBusiness->execute([$name, $category, $address, $slug, $businessId]);
        } else {
            $createBusiness->execute([$userId, $name, $category, $address, $slug]);
        }
    }

    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

echo "Created or reset five pending business demo accounts.\n";
echo "Shared password: {$password}\n";

