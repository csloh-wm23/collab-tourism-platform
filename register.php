<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$values = ['full_name' => '', 'email' => '', 'role' => 'tourist', 'business_name' => '', 'category' => 'Food & drink', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim((string)($_POST[$key] ?? $value));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } elseif ($values['full_name'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your name and a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($values['role'], ['tourist', 'business'], true)) {
        $error = 'Invalid account type.';
    } elseif ($values['role'] === 'business' && ($values['business_name'] === '' || $values['address'] === '')) {
        $error = 'Business name and address are required.';
    } else {
        try {
            $db = database();
            $db->beginTransaction();
            $status = $values['role'] === 'business' ? 'pending' : 'active';
            $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                mb_substr($values['full_name'], 0, 120),
                mb_strtolower($values['email']),
                password_hash($password, PASSWORD_DEFAULT),
                $values['role'],
                $status,
            ]);
            $userId = (int)$db->lastInsertId();

            if ($values['role'] === 'tourist') {
                $db->prepare('INSERT INTO tourist_profiles (user_id) VALUES (?)')->execute([$userId]);
            } else {
                $slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $values['business_name']), '-'));
                $slug = ($slugBase ?: 'business') . '-' . $userId;
                $db->prepare('INSERT INTO businesses (owner_user_id, name, category, address, verification_status, qr_slug) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, mb_substr($values['business_name'], 0, 160), mb_substr($values['category'], 0, 80), mb_substr($values['address'], 0, 500), 'pending', $slug]);
            }

            $db->commit();
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $exception) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getCode() === '23000' ? 'That email address is already registered.' : 'Registration failed. Check the database and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="icon" href="assets/logo.svg" type="image/svg+xml">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Create a TourLingo tourist or tourism business account.">
    <title>Create your account · TourLingo</title>
    <script>try{if(localStorage.getItem('jomcommunicate_theme')==='dark')document.documentElement.classList.add('dark-mode');}catch(error){}</script>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/assets/css/styles.css') ?: '1')) ?>">
</head>
<body class="auth-body">
    <aside class="auth-showcase">
        <a class="brand" href="index.php"><span class="brand-mark"><img src="assets/logo.svg" width="46" height="46" alt=""></span><span>TourLingo<small>Travel with confidence</small></span></a>
        <div class="auth-showcase-copy">
            <span class="eyebrow light-eyebrow">Start with TourLingo</span>
            <h1>Feel understood, wherever you go.</h1>
            <p>Create a personal travel space or connect your tourism business with multilingual visitors.</p>
            <div class="auth-points"><span><i></i>Save language and accessibility preferences</span><span><i></i>Keep destination packs ready offline</span><span><i></i>Give travellers approved business answers</span></div>
        </div>
        <small>TourLingo · Built for clearer journeys in Malaysia</small>
    </aside>
    <main class="auth-main">
        <section class="auth-card">
            <a class="brand-inline" href="index.php">← Back to TourLingo</a>
            <h1>Create your account</h1>
            <p class="muted">Choose the account that fits your journey.</p>
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="form-grid two-columns">
                    <label>Full name<input name="full_name" autocomplete="name" required value="<?= htmlspecialchars($values['full_name']) ?>" placeholder="Your name"></label>
                    <label>Email address<input name="email" type="email" autocomplete="email" required value="<?= htmlspecialchars($values['email']) ?>" placeholder="you@example.com"></label>
                </div>
                <label>Account type
                    <select name="role" id="roleSelect">
                        <option value="tourist" <?= $values['role'] === 'tourist' ? 'selected' : '' ?>>Traveller</option>
                        <option value="business" <?= $values['role'] === 'business' ? 'selected' : '' ?>>Tourism business</option>
                    </select>
                </label>
                <div id="touristBenefit" class="auth-benefit"><span class="action-icon aqua">⌖</span><div><strong>Traveller profile</strong><small>Use TourLingo immediately and personalise it around your needs.</small></div></div>
                <div id="businessFields">
                    <div class="auth-benefit"><span class="action-icon blue">⌂</span><div><strong>Business profile</strong><small>Your account becomes active after an administrator reviews it.</small></div></div>
                    <label>Business name<input name="business_name" value="<?= htmlspecialchars($values['business_name']) ?>"></label>
                    <div class="form-grid two-columns"><label>Category<select name="category"><option>Food & drink</option><option>Accommodation</option><option>Attraction</option><option>Transport</option><option>Tour operator</option></select></label><label>Address<textarea name="address" rows="2"><?= htmlspecialchars($values['address']) ?></textarea></label></div>
                </div>
                <div class="form-grid two-columns">
                    <label>Password<input name="password" type="password" minlength="8" autocomplete="new-password" required placeholder="At least 8 characters"></label>
                    <label>Confirm password<input name="confirm_password" type="password" minlength="8" autocomplete="new-password" required placeholder="Repeat your password"></label>
                </div>
                <button class="primary" type="submit">Create my TourLingo account</button>
            </form>
            <p class="auth-foot">Already registered? <a href="login.php">Log in</a></p>
        </section>
    </main>
    <script>
    const role=document.getElementById('roleSelect'),fields=document.getElementById('businessFields'),benefit=document.getElementById('touristBenefit');
    function sync(){const business=role.value==='business';fields.hidden=!business;benefit.hidden=business;}
    role.addEventListener('change',sync);sync();
    </script>
</body>
</html>
