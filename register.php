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
                $status
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
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getCode() === '23000' ? 'That email address is already registered.' : 'Registration failed. Check the database and try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create account · JomCommunicate</title>
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-body">
<main class="auth-card">
<a class="brand-inline" href="index.php">JC · JomCommunicate</a>
<h1>Create account</h1>
<p class="muted">Tourists can use the platform immediately. Business accounts require administrator approval.</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
<label>Full name<input name="full_name" required value="<?= htmlspecialchars($values['full_name']) ?>"></label>
<label>Email<input name="email" type="email" required value="<?= htmlspecialchars($values['email']) ?>"></label>
<label>Account type
<select name="role" id="roleSelect">
<option value="tourist" <?= $values['role']==='tourist'?'selected':'' ?>>Tourist</option>
<option value="business" <?= $values['role']==='business'?'selected':'' ?>>Tourism business</option>
</select></label>
<div id="businessFields">
<label>Business name<input name="business_name" value="<?= htmlspecialchars($values['business_name']) ?>"></label>
<label>Category<select name="category"><option>Food & drink</option><option>Accommodation</option><option>Attraction</option><option>Transport</option><option>Tour operator</option></select></label>
<label>Address<textarea name="address" rows="2"><?= htmlspecialchars($values['address']) ?></textarea></label>
</div>
<label>Password<input name="password" type="password" minlength="8" required></label>
<label>Confirm password<input name="confirm_password" type="password" minlength="8" required></label>
<button class="primary" type="submit">Create account</button>
</form>
<p class="auth-foot">Already registered? <a href="login.php">Log in</a></p>
</main>
<script>
const role=document.getElementById('roleSelect'), fields=document.getElementById('businessFields');
function sync(){fields.hidden=role.value!=='business';} role.addEventListener('change',sync); sync();
</script>
</body></html>
