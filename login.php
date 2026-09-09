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
                        $db->prepare('UPDATE users SET failed_login_attempts=? WHERE id=?')->execute([$state['attempts'], (int)$user['id']]);
                        $error = 'Incorrect email or password.';
                    }
                } else {
                    $error = 'Incorrect email or password.';
                }
            } elseif ($user['status'] === 'suspended') {
                $error = 'This account is suspended.';
            } else {
                $db->prepare('UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=?')->execute([(int)$user['id']]);
                unset($user['password_hash'], $user['failed_login_attempts'], $user['locked_until']);
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $exception) {
            $error = 'Could not connect to the database.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Sign in to your TourLingo travel communication account.">
    <title>Welcome back · TourLingo</title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/assets/css/styles.css') ?: '1')) ?>">
</head>
<body class="auth-body">
    <aside class="auth-showcase">
        <a class="brand" href="index.php"><span class="brand-mark">T</span><span>TourLingo<small>Travel with confidence</small></span></a>
        <div class="auth-showcase-copy">
            <span class="eyebrow light-eyebrow">Welcome back</span>
            <h1>Your journey speaks every language.</h1>
            <p>Return to your saved phrases, travel preferences and business communication tools.</p>
            <div class="auth-points"><span><i></i>Five supported travel languages</span><span><i></i>Personalised phrase packs</span><span><i></i>Private and secure account access</span></div>
        </div>
        <small>TourLingo · Built for clearer journeys in Malaysia</small>
    </aside>
    <main class="auth-main">
        <section class="auth-card">
            <a class="brand-inline" href="index.php">← Back to TourLingo</a>
            <span class="eyebrow">Account access</span>
            <h1>Log in</h1>
            <p class="muted">Continue where your last journey left off.</p>
            <?php if (isset($_GET['registered'])): ?><div class="alert good">Account created. You can now log in.</div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <label>Email address<input type="email" name="email" autocomplete="email" required autofocus placeholder="you@example.com"></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"></label>
                <button class="primary" type="submit">Log in to TourLingo</button>
            </form>
            <p class="auth-foot">New to TourLingo? <a href="register.php">Create an account</a></p>
        </section>
    </main>
</body>
</html>
