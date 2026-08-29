<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/security.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your form session expired. Please try again.';
    } else {
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        try {
            $db = database();
            $stmt = $db->prepare('SELECT id, full_name, email, password_hash, role, status, preferred_language, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $locked = $user && !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time();
            if ($locked) {
                $minutes = max(1, (int)ceil((strtotime((string)$user['locked_until']) - time()) / 60));
                $error = "Too many failed attempts. Try again in {$minutes} minute(s).";
            } elseif (!$user || !password_verify($password, (string)$user['password_hash'])) {
                if ($user) {
                    $state = failed_login_state((int)$user['failed_login_attempts']);
                    if ($state['locked']) {
                        $db->prepare('UPDATE users SET failed_login_attempts=0, locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?')->execute([(int)$user['id']]);
                        $error = 'Too many failed attempts. Try again in 15 minutes.';
                    } else {
                        $db->prepare('UPDATE users SET failed_login_attempts=? WHERE id=?')->execute([$state['attempts'],(int)$user['id']]);
                        $error = 'Incorrect email or password.';
                    }
                } else {
                    $error = 'Incorrect email or password.';
                }
            } elseif ($user['status'] === 'suspended') {
                $error = 'This account is suspended.';
            } else {
                $db->prepare('UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=?')->execute([(int)$user['id']]);
                unset($user['password_hash'],$user['failed_login_attempts'],$user['locked_until']);
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Could not connect to the database.';
        }
    }
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log in · JomCommunicate</title><link rel="stylesheet" href="assets/css/styles.css">
</head><body class="auth-body">
<main class="auth-card">
<a class="brand-inline" href="index.php">JC · JomCommunicate</a>
<h1>Log in</h1>
<?php if (isset($_GET['registered'])): ?><div class="alert good">Account created. You can now log in.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
<label>Email<input type="email" name="email" required autofocus></label>
<label>Password<input type="password" name="password" required></label>
<button class="primary" type="submit">Log in</button>
</form>
<p class="auth-foot">Need an account? <a href="register.php">Register</a></p>
</main></body></html>
