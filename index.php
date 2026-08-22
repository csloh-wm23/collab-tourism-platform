<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$user = current_user();
$role = $user['role'] ?? 'guest';
$name = $user['full_name'] ?? 'Guest visitor';
$businessVerification = null;
if ($user && $role === 'business') {
    try {
        $stmt = database()->prepare('SELECT verification_status FROM businesses WHERE owner_user_id=? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $businessVerification = $stmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        error_log('Unable to load business verification status: ' . $e->getMessage());
    }
}
$businessApproved = $role === 'business'
    && ($user['status'] ?? '') === 'active'
    && $businessVerification === 'approved';
$initials = 'GV';
if ($user) {
    $parts = preg_split('/\s+/', trim((string)$user['full_name'])) ?: [];
    $initials = strtoupper(substr((string)($parts[0] ?? 'U'),0,1) . substr((string)($parts[count($parts)-1] ?? ''),0,1));
}
$year = date('Y');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="JomCommunicate Malaysia tourism communication platform">
<title>JomCommunicate</title>
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body data-role="<?= htmlspecialchars($role) ?>" data-authenticated="<?= $user?'1':'0' ?>">
<div class="app-shell">
<aside class="sidebar">
<a class="brand" href="#communication"><span class="brand-mark">JC</span><span>JomCommunicate<small>Travel without language barriers</small></span></a>
<nav>
<button class="nav-link active" data-page="communication">💬 Communication</button>
<button class="nav-link" data-page="assistance">🧭 Smart assistance</button>
<button class="nav-link" data-page="journey">🧳 My journey</button>
<?php if ($businessApproved): ?><button class="nav-link" data-page="business">🏪 Business portal</button><?php endif; ?>
<?php if (in_array($role,['editor','admin'],true) && ($user['status']??'')==='active'): ?><button class="nav-link" data-page="insights">📊 Insights</button><?php endif; ?>
<?php if ($role==='admin' && ($user['status']??'')==='active'): ?><button class="nav-link" data-page="admin">⚙️ Administration</button><?php endif; ?>
</nav>
<div class="sidebar-foot"><span class="status-dot"></span> PHP · MySQL · Azure Speech</div>
</aside>

<main class="main-area">
<header class="topbar">
<button id="menuButton" class="icon-button">☰</button>
<div><strong>Malaysia visitor support</strong><small id="todayLabel"></small></div>
<div class="top-actions">
<button id="contrastButton" class="icon-button" title="High contrast">◐</button>
<div class="profile-chip"><span><?= htmlspecialchars($initials) ?></span><div><strong><?= htmlspecialchars($name) ?></strong><small><?= htmlspecialchars(ucfirst($role)) ?></small></div></div>
<?php if ($user): ?>
<form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>"><button class="small-button">Log out</button></form>
<?php else: ?><a class="small-button" href="login.php">Log in</a><a class="small-button primary-link" href="register.php">Register</a><?php endif; ?>
</div>
</header>

<div class="content-wrap">
<?php if ($role === 'business' && !$businessApproved): ?>
<div class="alert warning account-notice" role="status">
<?php if (($user['status']??'') === 'suspended'): ?>
<h2>Business account suspended</h2><p>Protected account and Business Portal actions are unavailable. Contact an administrator for help.</p>
<?php elseif ($businessVerification === 'rejected'): ?>
<h2>Business registration rejected</h2><p>The Business Portal is unavailable. Contact an administrator if the registration details need to be reviewed.</p>
<?php else: ?>
<h2>Pending administrator approval</h2><p>You can use the public communication tools, but Business Portal functionality remains locked until an administrator approves the registration.</p>
<?php endif; ?>
</div>
<?php elseif ($user && ($user['status']??'') === 'suspended'): ?>
<div class="alert warning account-notice" role="status"><h2>Account suspended</h2><p>Protected account functions are unavailable. Contact an administrator for help.</p></div>
<?php endif; ?>
<section class="page active" id="communication">
<div class="page-heading"><div><span class="eyebrow">Real-time tourism communication</span><h1>Translate and speak</h1><p>Text translation demo with Azure neural speech playback for Malay and Malaysian Tamil.</p></div><span class="pill" id="speechBadge">Azure TTS ready after setup</span></div>
<div class="grid-two">
<article class="card-panel">
<h2>Translate a message</h2>
<div class="grid-two compact-grid">
<label>From<select id="sourceLanguage"><option value="en">English</option><option value="ms">Bahasa Melayu</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></label>
<label>To<select id="targetLanguage"><option value="ms">Bahasa Melayu</option><option value="en">English</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></label>
</div>
<textarea id="sourceText" maxlength="500" rows="5">Where is the nearest train station?</textarea>
<div class="field-footer"><button id="listenInput" class="link-button">🎙 Speak</button><span><span id="characterCount"></span>/500</span></div>
<div class="button-row"><button id="swapLanguages" class="secondary">⇄ Swap</button><button id="translateButton" class="primary">Translate</button></div>
</article>

<article class="card-panel">
<h2>Translation</h2>
<div id="translationResult" class="translation-result">Stesen kereta api terdekat di mana?</div>
<div class="button-row">
<button id="speakResult" class="secondary">🔊 Azure voice</button>
<button id="copyResult" class="secondary">⧉ Copy</button>
<button id="savePhrase" class="primary">＋ Save phrase</button>
</div>
<p class="muted small">Translation itself is still the project demo dictionary. Azure is used for text-to-speech output.</p>
</article>
</div>
<div class="grid-two mt">
<article class="card-panel"><h2>Recent translations</h2><div id="recentTranslations" class="record-list"></div></article>
<article class="card-panel"><h2>Saved phrases</h2><div id="savedPhrases" class="record-list"></div></article>
</div>
</section>

<section class="page" id="assistance">
<div class="page-heading"><div><span class="eyebrow">Smart tourism assistance</span><h1>Help for common travel situations</h1></div></div>
<div class="grid-two">
<article class="card-panel"><h2>Guided scenario</h2><select id="scenarioSelect"><option value="restaurant">Restaurant</option><option value="transport">Public transport</option><option value="hotel">Hotel</option><option value="shopping">Shopping</option></select><div id="scenarioSteps" class="scenario-steps"></div></article>
<article class="card-panel danger-card"><h2>Emergency card</h2><p>I need help / Saya perlukan bantuan</p><button id="fullscreenEmergency" class="danger">Open large emergency card</button></article>
</div>
</section>

<section class="page" id="journey">
<div class="page-heading"><div><span class="eyebrow">Personalised tourist and journey</span><h1>My journey</h1><p><?= $user?'Your account can save server-side records.':'You are using guest access. Saved phrases stay only in this browser.' ?></p></div></div>
<?php if (!$user || ($role === 'tourist' && ($user['status']??'') === 'active')): ?>
<div class="card-panel"><h2>Privacy choices</h2><label class="check-row"><input type="checkbox" id="historyConsent" checked> Save translation history</label><label class="check-row"><input type="checkbox" id="analyticsConsent"> Share anonymous usage data</label></div>
<?php else: ?>
<div class="card-panel"><h2>Journey records</h2><p class="muted">Personal translation records are scoped to tourist and business accounts. Anonymous analytics never includes conversation text.</p></div>
<?php endif; ?>
</section>

<?php if ($businessApproved): ?>
<section class="page" id="business">
<div class="page-heading"><div><span class="eyebrow">Tourism business communication</span><h1>Business portal</h1><p>Profile and multilingual phrases are now stored in their dedicated MySQL tables.</p></div></div>
<div class="grid-two">
<article class="card-panel"><h2>Business profile</h2><label>Name<input id="businessName"></label><label>Category<input id="businessCategory"></label><label>Address<textarea id="businessAddress" rows="3"></textarea></label><button id="saveBusiness" class="primary">Save profile</button></article>
<article class="card-panel"><h2>Add multilingual phrase</h2><label>English phrase<input id="phraseSource"></label><label>Translation<input id="phraseTranslated"></label><label>Target language<select id="phraseTarget"><option value="ms">Malay</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></label><label>Category<input id="phraseCategory" value="General"></label><button id="addBusinessPhrase" class="primary">Add phrase</button></article>
</div>
<article class="card-panel mt"><h2>Published phrases</h2><div id="businessPhrases"></div></article>
</section>
<?php endif; ?>

<?php if (in_array($role,['editor','admin'],true) && ($user['status']??'')==='active'): ?>
<section class="page" id="insights">
<div class="page-heading"><div><span class="eyebrow">Communication intelligence</span><h1>Service insights</h1></div></div>
<div class="stat-grid"><article><strong id="insightTranslations">—</strong><small>Translation records</small></article><article><strong id="insightPhrases">—</strong><small>Saved and business phrases</small></article><article><strong id="insightBusinesses">—</strong><small>Business accounts</small></article><article><strong id="insightPending">—</strong><small>Pending businesses</small></article></div>
</section>
<?php endif; ?>

<?php if ($role==='admin' && ($user['status']??'')==='active'): ?>
<section class="page" id="admin">
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Platform operations</h1></div></div>
<div class="stat-grid"><article><strong id="adminUsers">—</strong><small>Active users</small></article><article><strong id="adminBusinesses">—</strong><small>Approved businesses</small></article><article><strong id="adminPending">—</strong><small>Pending businesses</small></article><article><strong id="adminTranslations">—</strong><small>Translations</small></article></div>
<article class="card-panel mt"><h2>Pending business registrations</h2><div id="pendingBusinesses" class="record-list"></div></article>
</section>
<?php endif; ?>

<footer>© <?= htmlspecialchars((string)$year) ?> JomCommunicate · Malaysia Language Real-time Communication System</footer>
</div>
</main>
</div>

<div id="toast" class="toast-message" role="status"></div>
<div id="emergencyOverlay" class="emergency-overlay" aria-hidden="true"><button id="closeEmergency">×</button><strong>I NEED HELP</strong><strong>SAYA PERLUKAN BANTUAN</strong><span>Call 999</span></div>

<script>
window.JOM = {
  csrf: <?= json_encode(csrf_token()) ?>,
  authenticated: <?= $user?'true':'false' ?>,
  role: <?= json_encode($role) ?>
};
</script>
<script src="assets/js/app.js"></script>
</body></html>
