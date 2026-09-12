<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$password = 'TourLingoDemo#2026';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$users = [
    ['Demo Traveller', 'tourist.demo@tourlingo.test', 'tourist', 'active'],
    ['Pending Business Owner', 'business.pending@tourlingo.test', 'business', 'pending'],
    ['Approved Business Owner', 'business.approved@tourlingo.test', 'business', 'active'],
    ['Rejected Business Owner', 'business.rejected@tourlingo.test', 'business', 'pending'],
    ['Demo Insights Editor', 'editor.demo@tourlingo.test', 'editor', 'active'],
    ['Demo System Administrator', 'admin.demo@tourlingo.test', 'admin', 'active'],
    ['Suspended Demo Traveller', 'suspended.demo@tourlingo.test', 'tourist', 'suspended'],
    ['Aina Rahman', 'test.cafe@gmail.com', 'business', 'pending'],
    ['Daniel Lee', 'test.hotel@gmail.com', 'business', 'pending'],
    ['Siti Hana', 'test.transport@gmail.com', 'business', 'pending'],
    ['Arjun Kumar', 'test.touragency@gmail.com', 'business', 'pending'],
    ['Mei Ling Tan', 'test.shop@gmail.com', 'business', 'pending'],
];
$businesses = [
    ['business.pending@tourlingo.test', 'Pending Tourism Cafe', 'Food & drink', 'Jalan Bukit Bintang, Kuala Lumpur', 'pending', 'pending-tourism-cafe-demo', 1],
    ['business.approved@tourlingo.test', 'TourLingo Demo Food House', 'Food & drink', 'Jalan Bukit Bintang, Kuala Lumpur', 'approved', 'tourlingo-demo-food-house', 1],
    ['business.rejected@tourlingo.test', 'Rejected Demo Restaurant', 'Food & drink', 'Jalan Bukit Bintang, Kuala Lumpur', 'rejected', 'rejected-demo-restaurant', 1],
    ['test.cafe@gmail.com', 'Rasa Malaysia Cafe', 'Food & drink', 'Jalan Alor, Kuala Lumpur', 'pending', 'rasa-malaysia-cafe-demo', 0],
    ['test.hotel@gmail.com', 'City Lights Boutique Hotel', 'Accommodation', 'Bukit Bintang, Kuala Lumpur', 'pending', 'city-lights-hotel-demo', 0],
    ['test.transport@gmail.com', 'KL Easy Ride', 'Transportation', 'KL Sentral, Kuala Lumpur', 'pending', 'kl-easy-ride-demo', 0],
    ['test.touragency@gmail.com', 'Discover Malaysia Tours', 'Tour operator', 'Petaling Street, Kuala Lumpur', 'pending', 'discover-malaysia-tours-demo', 0],
    ['test.shop@gmail.com', 'Local Treasures Market', 'Shopping', 'Central Market, Kuala Lumpur', 'pending', 'local-treasures-market-demo', 0],
];

$db = database();
$db->beginTransaction();

try {
    $saveUser = $db->prepare(
        'INSERT INTO users (full_name,email,password_hash,role,status,preferred_language,failed_login_attempts,locked_until)
         VALUES (?,?,?,?,?,\'en\',0,NULL)
         ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),password_hash=VALUES(password_hash),
           role=VALUES(role),status=VALUES(status),failed_login_attempts=0,locked_until=NULL'
    );
    $findUser = $db->prepare('SELECT id FROM users WHERE email=?');
    $findBusiness = $db->prepare('SELECT id FROM businesses WHERE owner_user_id=? LIMIT 1');
    $updateBusiness = $db->prepare(
        'UPDATE businesses SET name=?,category=?,address=?,verification_status=?,qr_slug=?,is_public=? WHERE id=?'
    );
    $createBusiness = $db->prepare(
        'INSERT INTO businesses (owner_user_id,name,category,address,verification_status,qr_slug,is_public)
         VALUES (?,?,?,?,?,?,?)'
    );

    foreach ($users as [$name, $email, $role, $status]) {
        $saveUser->execute([$name, $email, $passwordHash, $role, $status]);
    }

    $businessIds = [];
    foreach ($businesses as [$email, $name, $category, $address, $verification, $slug, $isPublic]) {
        $findUser->execute([$email]);
        $userId = (int) $findUser->fetchColumn();
        $findBusiness->execute([$userId]);
        $businessId = (int) $findBusiness->fetchColumn();

        if ($businessId > 0) {
            $updateBusiness->execute([$name, $category, $address, $verification, $slug, $isPublic, $businessId]);
        } else {
            $createBusiness->execute([$userId, $name, $category, $address, $verification, $slug, $isPublic]);
            $businessId = (int) $db->lastInsertId();
        }
        $businessIds[$email] = $businessId;
    }

    $approvedId = $businessIds['business.approved@tourlingo.test'];
    $profile = $db->prepare(
        'UPDATE businesses SET description=?,service_details=?,payment_methods=?,menu_details=?,facility_details=? WHERE id=?'
    );
    $profile->execute([
        'A welcoming Malaysian restaurant serving local favourites.',
        'Dine-in, takeaway, vegetarian options and family-friendly service.',
        'Cash, cards and QR payments.',
        'Halal dishes, vegetarian choices and daily specials.',
        'Accessible seating, family tables and a prayer-friendly space.',
        $approvedId,
    ]);

    $phrase = $db->prepare(
        "INSERT INTO business_phrases (business_id,source_language,target_language,source_text,translated_text,suggested_reply,category,is_published)
         SELECT ?,'en','ms',?,?,?,?,1 WHERE NOT EXISTS
         (SELECT 1 FROM business_phrases WHERE business_id=? AND source_text=?)"
    );
    $phrase->execute([$approvedId, 'Does this contain peanuts?', 'Adakah ini mengandungi kacang tanah?', 'No, this dish does not contain peanuts.', 'Food', $approvedId, 'Does this contain peanuts?']);

    $faq = $db->prepare(
        "INSERT INTO business_faqs (business_id,question,answer,language_code,is_published)
         SELECT ?,?,?,'en',1 WHERE NOT EXISTS
         (SELECT 1 FROM business_faqs WHERE business_id=? AND question=?)"
    );
    $faq->execute([$approvedId, 'Do you accept cashless payments?', 'Yes, we accept cards and QR payments.', $approvedId, 'Do you accept cashless payments?']);

    $term = $db->prepare(
        "INSERT INTO business_terms (business_id,term,explanation,language_code,is_published)
         VALUES (?,?,?,'en',1) ON DUPLICATE KEY UPDATE explanation=VALUES(explanation),is_published=1"
    );
    $term->execute([$approvedId, 'Mamak', 'A casual Malaysian Indian Muslim eatery, often open late.']);

    $sampleQuestions = [
        ['11111111111111111111111111111111', 'Opening hours', 'en', 'Are you open after 9 PM?', 'new', null],
        ['22222222222222222222222222222222', 'Dietary requirement', 'ms', 'Do you have a halal vegetarian meal?', 'in_progress', null],
        ['33333333333333333333333333333333', 'Accessibility', 'en', 'Is the entrance wheelchair accessible?', 'answered', 'Yes. The main entrance has step-free access.'],
    ];
    $sampleQuestion = $db->prepare(
        'INSERT INTO business_interactions (business_id,category,language_code,question_label,public_token,status,reply_text,answered_at)
         SELECT ?,?,?,?,?,?,?,IF(?="answered",NOW(),NULL) WHERE NOT EXISTS
         (SELECT 1 FROM business_interactions WHERE public_token=?)'
    );
    foreach ($sampleQuestions as [$token, $category, $language, $question, $status, $reply]) {
        $sampleQuestion->execute([$approvedId, $category, $language, $question, $token, $status, $reply, $status, $token]);
    }

    $db->exec("INSERT IGNORE INTO tourist_profiles (user_id) SELECT id FROM users WHERE role='tourist'");
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}

echo "Created or reset all 12 TourLingo demo accounts.\n";
echo "Shared password: {$password}\n";
