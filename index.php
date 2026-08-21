<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$user = current_user();
$role = $user['role'] ?? 'guest';
$name = $user['full_name'] ?? 'Guest visitor';
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
<?php if (in_array($role,['business','admin'],true)): ?><button class="nav-link" data-page="business">🏪 Business portal</button><?php endif; ?>
<?php if (in_array($role,['editor','admin'],true)): ?><button class="nav-link" data-page="insights">📊 Insights</button><?php endif; ?>
<?php if ($role==='admin'): ?><button class="nav-link" data-page="admin">⚙️ Administration</button><?php endif; ?>
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
<div class="field-footer"><button id="listenInput" class="link-button">🎙 Speak</button><span><span id="characterCount">39</span>/500</span></div>
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
<div class="card-panel"><h2>Privacy choices</h2><label class="check-row"><input type="checkbox" id="historyConsent" checked> Save translation history</label><label class="check-row"><input type="checkbox" id="analyticsConsent"> Share anonymous usage data</label></div>
</section>

<?php if (in_array($role,['business','admin'],true)): ?>
<section class="page" id="business">
<div class="page-heading"><div><span class="eyebrow">Tourism business communication</span><h1>Business portal</h1><p>Profile and multilingual phrases are now stored in their dedicated MySQL tables.</p></div></div>
<?php if ($role==='business' && ($user['status']??'')==='pending'): ?><div class="alert warning">Your business registration is pending administrator approval.</div><?php endif; ?>
<div class="grid-two">
<article class="card-panel"><h2>Business profile</h2><label>Name<input id="businessName"></label><label>Category<input id="businessCategory"></label><label>Address<textarea id="businessAddress" rows="3"></textarea></label><button id="saveBusiness" class="primary">Save profile</button></article>
<article class="card-panel"><h2>Add multilingual phrase</h2><label>English phrase<input id="phraseSource"></label><label>Translation<input id="phraseTranslated"></label><label>Target language<select id="phraseTarget"><option value="ms">Malay</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></label><label>Category<input id="phraseCategory" value="General"></label><button id="addBusinessPhrase" class="primary">Add phrase</button></article>
</div>
<article class="card-panel mt"><h2>Published phrases</h2><div id="businessPhrases"></div></article>
</section>
<?php endif; ?>

<?php if (in_array($role,['editor','admin'],true)): ?>
<section class="page" id="insights">
<div class="page-heading"><div><span class="eyebrow">Communication intelligence</span><h1>Service insights</h1></div></div>
<div class="stat-grid"><article><strong id="insightTranslations">—</strong><small>Translation records</small></article><article><strong>Context</strong><small>Expand with reporting queries later</small></article></div>
</section>
<?php endif; ?>

<?php if ($role==='admin'): ?>
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
