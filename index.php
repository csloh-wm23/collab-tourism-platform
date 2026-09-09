<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

$user = current_user();
$role = $user['role'] ?? 'guest';
$name = $user['full_name'] ?? 'Guest traveller';
$firstName = trim(explode(' ', $name)[0] ?? $name);
$profileInitial = mb_strtoupper(mb_substr($name, 0, 1));
$profileImage = is_string($user['profile_image'] ?? null) ? (string)$user['profile_image'] : '';
$approved = false;
$businessStatus = null;

if ($user && $role === 'business') {
    try {
        $statement = database()->prepare('SELECT verification_status FROM businesses WHERE owner_user_id=?');
        $statement->execute([(int)$user['id']]);
        $businessStatus = $statement->fetchColumn() ?: null;
        $approved = $businessStatus === 'approved' && ($user['status'] ?? '') === 'active';
    } catch (Throwable $exception) {
        error_log('Unable to load business status: ' . $exception->getMessage());
    }
}

$staff = in_array($role, ['editor', 'admin'], true) && ($user['status'] ?? '') === 'active';

function language_options(bool $includeAutomatic = false): string
{
    $languages = ($includeAutomatic ? ['auto' => 'Automatic detection'] : []) + [
        'en' => 'English',
        'ms' => 'Bahasa Malaysia',
        'zh' => 'Mandarin Chinese',
        'id' => 'Indonesian',
        'th' => 'Thai',
    ];
    $html = '';
    foreach ($languages as $value => $label) {
        $html .= '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}

function nav_icon(string $name): string
{
    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        'translate' => '<path d="M4 5h10M9 3v2c0 4-2 7-5 9"/><path d="M6 10c2 2 4 3 7 4"/><path d="m14 20 4-9 4 9m-6.5-3h5"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
        'journey' => '<path d="M6 21V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14"/><path d="M9 5V3h6v2M4 10h16M9 13v4m6-4v4"/>',
        'store' => '<path d="M4 10v10h16V10"/><path d="m3 10 2-6h14l2 6"/><path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 20v-6h6v6"/>',
        'chart' => '<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
        'admin' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? $icons['home']) . '</svg>';
}

function nav_button(string $page, string $label, string $icon, bool $active = false): string
{
    return '<button class="nav-link' . ($active ? ' active' : '') . '" type="button" data-page="' . htmlspecialchars($page) . '">' . nav_icon($icon) . '<span>' . htmlspecialchars($label) . '</span></button>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="TourLingo makes multilingual travel communication simple, personal and accessible.">
    <meta name="theme-color" content="#082f49">
    <title>TourLingo · Travel with confidence</title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/assets/css/styles.css') ?: '1')) ?>">
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="app-shell">
    <aside class="sidebar" id="mainNavigation" aria-hidden="false">
        <button id="closeMenuButton" class="sidebar-close" type="button" aria-label="Close navigation">×</button>
        <a class="brand" href="#<?= $user ? 'home' : 'communication' ?>" aria-label="TourLingo <?= $user ? 'overview' : 'translator' ?>">
            <span class="brand-mark">T</span>
            <span>TourLingo<small>Travel with confidence</small></span>
        </a>
        <nav aria-label="Primary navigation">
            <span class="nav-label">Explore</span>
            <?php if ($user): ?>
                <?= nav_button('home', 'Overview', 'home', true) ?>
            <?php endif; ?>
            <?= nav_button('communication', 'Translate', 'translate', !$user) ?>
            <?php if ($user): ?>
                <?= nav_button('assistance', 'Travel assistant', 'compass') ?>
            <?php endif; ?>
            <?php if ($user && $role === 'tourist'): ?>
                <?= nav_button('journey', 'My journey', 'journey') ?>
            <?php endif; ?>
            <?php if ($approved || ($role === 'business' && !$approved) || $staff): ?>
                <span class="nav-label nav-label-spaced">Workspace</span>
            <?php endif; ?>
            <?php if ($approved): ?>
                <?= nav_button('business', 'Business studio', 'store') ?>
            <?php elseif ($role === 'business'): ?>
                <?= nav_button('application', 'Business application', 'store') ?>
            <?php endif; ?>
            <?php if ($staff): ?>
                <?= nav_button('insights', 'Insights', 'chart') ?>
            <?php endif; ?>
            <?php if ($role === 'admin' && $staff): ?>
                <?= nav_button('admin', 'Administration', 'admin') ?>
            <?php endif; ?>
            <?php if ($user): ?>
                <span class="nav-label nav-label-spaced">Personal</span>
                <?= nav_button('profile', 'Profile & settings', 'profile') ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot"><span class="status-dot"></span><span><strong>Services connected</strong><small>Translation and travel tools ready</small></span></div>
    </aside>

    <button id="sidebarBackdrop" class="sidebar-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>
    <main class="main-area">
        <header class="topbar">
            <div class="topbar-leading">
                <button id="menuButton" class="icon-button menu-button" type="button" aria-label="Open navigation" aria-controls="mainNavigation" aria-expanded="false"><span></span><span></span><span></span></button>
                <div class="topbar-title"><small>TourLingo</small><strong id="currentPageLabel"><?= $user ? 'Overview' : 'Translate' ?></strong></div>
            </div>
            <div class="top-actions">
                <span id="todayLabel" class="today-label"></span>
                <button id="contrastButton" class="icon-button" type="button" aria-label="Enable dark mode" aria-pressed="false" title="Enable dark mode">☾</button>
                <?php if ($user): ?>
                    <button class="profile-chip profile-button" type="button" data-page="profile" aria-label="Open profile settings"><span class="chip-avatar" id="topbarProfileAvatar"><?php if ($profileImage !== ''): ?><img src="<?= htmlspecialchars($profileImage) ?>" alt=""><?php else: ?><?= htmlspecialchars($profileInitial) ?><?php endif; ?></span><div><strong id="profileNameLabel"><?= htmlspecialchars($name) ?></strong><small><?= htmlspecialchars(ucfirst($role)) ?></small></div></button>
                    <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>"><button class="small-button quiet-button">Log out</button></form>
                <?php else: ?>
                    <div class="profile-chip guest-chip"><span>G</span><div><strong>Guest traveller</strong><small>Guest</small></div></div>
                    <a class="small-button quiet-button" href="login.php">Log in</a>
                    <a class="small-button primary-link" href="register.php">Create account</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="content-wrap">
            <?php if ($user): ?>
            <section class="page active" id="home" data-title="Overview">
                <div class="welcome-panel">
                    <div class="welcome-copy">
                        <span class="eyebrow light-eyebrow">Your travel companion</span>
                        <h1>Welcome<?= $user ? ', ' . htmlspecialchars($firstName) : '' ?>.</h1>
                        <p>Translate, prepare and get local help without losing the moment.</p>
                        <div class="button-row welcome-actions"><button class="primary bright-primary" type="button" data-page="communication">Start translating</button><button class="secondary glass-button" type="button" data-page="assistance">Open travel assistant</button></div>
                    </div>
                    <div class="welcome-visual" aria-hidden="true"><div class="route-card route-card-one"><span>EN</span><i></i><strong>BM</strong></div><div class="route-card route-card-two"><span>Ask</span><i></i><strong>Go</strong></div><div class="route-pin">⌖</div></div>
                </div>
                <div class="section-heading compact-heading"><div><span class="eyebrow">Quick access</span><h2>What would you like to do?</h2></div></div>
                <div class="action-grid">
                    <button class="action-card" type="button" data-page="communication"><span class="action-icon aqua">文</span><strong>Translate a message</strong><small>Text, voice and two-way conversation</small><b>Open translator →</b></button>
                    <button class="action-card" type="button" data-page="assistance"><span class="action-icon blue">✦</span><strong>Prepare for a situation</strong><small>Restaurants, hotels, transport and emergencies</small><b>Choose assistance →</b></button>
                    <?php if ($role === 'tourist'): ?>
                        <button class="action-card" type="button" data-page="journey"><span class="action-icon coral">⌖</span><strong>Plan my journey</strong><small>Saved packs, preferences and useful phrases</small><b>View journey →</b></button>
                    <?php elseif ($approved): ?>
                        <button class="action-card" type="button" data-page="business"><span class="action-icon coral">⌂</span><strong>Manage my business</strong><small>Public profile, phrases and visitor questions</small><b>Open studio →</b></button>
                    <?php elseif ($staff): ?>
                        <button class="action-card" type="button" data-page="insights"><span class="action-icon coral">↗</span><strong>Review service insights</strong><small>Anonymous usage patterns and opportunities</small><b>View insights →</b></button>
                    <?php else: ?>
                        <button class="action-card" type="button" data-page="application"><span class="action-icon coral">⌂</span><strong>Track my application</strong><small>Review and update your business submission</small><b>View application →</b></button>
                    <?php endif; ?>
                </div>
                <div class="home-info-grid"><article class="info-card emergency-info"><span class="info-symbol">999</span><div><strong>Emergency in Malaysia</strong><p>Use the bilingual emergency card to show essential details clearly.</p></div><button type="button" data-page="assistance">Prepare card</button></article><article class="info-card"><span class="info-symbol soft">5</span><div><strong>Languages available</strong><p>English, Bahasa Malaysia, Mandarin, Indonesian and Thai.</p></div></article></div>
            </section>
            <?php endif; ?>

            <section class="page<?= !$user ? ' active' : '' ?>" id="communication" data-title="Translate">
                <div class="page-heading"><div><span class="eyebrow">Live communication</span><h1>Translate and speak</h1><p>Clear, confident conversations wherever your journey takes you.</p></div><button id="largeMessage" class="secondary" type="button">Large-screen message</button></div>
                <div class="translation-workspace">
                    <article class="card-panel input-panel">
                        <div class="card-kicker"><span>01</span><div><h2>Your message</h2><p>Enter or speak the message you want to translate.</p></div></div>
                        <div class="language-row"><label>From<select id="sourceLanguage"><?= language_options(true) ?></select></label><button id="swapLanguages" class="swap-button" type="button" aria-label="Swap languages">⇄</button><label>To<select id="targetLanguage"><?= language_options() ?></select></label></div>
                        <textarea id="sourceText" class="message-input" maxlength="500" rows="6" placeholder="Type what you want to say…"></textarea>
                        <div class="field-footer">
                            <div class="voice-controls">
                                <button id="listenInput" class="voice-start" type="button" aria-pressed="false">
                                    <span class="voice-button-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="19" height="19"><path d="M12 15a4 4 0 0 0 4-4V6a4 4 0 1 0-8 0v5a4 4 0 0 0 4 4Zm7-4a1 1 0 0 0-2 0 5 5 0 0 1-10 0 1 1 0 0 0-2 0 7 7 0 0 0 6 6.92V20H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-2.08A7 7 0 0 0 19 11Z"/></svg></span>
                                    <span class="voice-button-copy"><strong>Start speaking</strong><small>Use your microphone</small></span>
                                </button>
                                <div id="voiceActive" class="voice-active" hidden>
                                    <span class="recording-pulse" aria-hidden="true"></span>
                                    <span class="voice-live-copy" aria-live="polite"><strong>Speaking…</strong><small>Keep talking until you are finished</small></span>
                                    <span class="voice-wave" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                                    <button id="finishSpeaking" class="voice-finish" type="button">Finish speaking</button>
                                </div>
                            </div>
                            <span class="character-counter"><span id="characterCount">0</span>/500</span>
                        </div>
                        <div class="panel-actions"><label class="check-row"><input type="checkbox" id="twoWayMode"> Two-way conversation mode</label><button id="translateButton" class="primary translate-cta">Translate message</button></div>
                    </article>
                    <article class="card-panel output-panel">
                        <div class="card-kicker"><span>02</span><div><h2>Translation</h2><p>Ready to play, copy or save.</p></div></div>
                        <div id="translationResult" class="translation-result">Your translation will appear here.</div>
                        <div id="translationMeta" class="meta-box">Language and confidence appear after translation.</div>
                        <div id="translationAlternatives" class="record-list"></div>
                        <div class="button-row result-actions"><button id="speakResult" class="secondary" disabled>Voice</button><button id="copyResult" class="secondary" disabled>Copy</button><button id="savePhrase" class="primary" disabled>Save</button><button id="reportTranslation" class="danger ghost-danger" disabled>Report unclear</button></div>
                        <div id="twoWayReplies" class="chip-row"></div>
                    </article>
                </div>
                <div class="resource-grid mt-large"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Local context</span><h2>Malaysian terminology</h2></div></div><div id="glossaryList" class="record-list"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Your library</span><h2>Saved communication</h2></div></div><div id="recentTranslations" class="record-list"></div><h3>Favourites</h3><div id="savedPhrases" class="record-list"></div></article></div>
            </section>

            <?php if ($user): ?>
            <section class="page" id="assistance" data-title="Travel assistant">
                <div class="page-heading"><div><span class="eyebrow">Situation-ready support</span><h1>Your travel assistant</h1><p>Prepare the right words before you need them.</p></div></div>
                <div class="assistant-layout">
                    <article class="card-panel assistant-builder"><div class="card-kicker"><span>✦</span><div><h2>Situation guide</h2><p>Build a useful phrase pack for your next stop.</p></div></div><div class="form-grid three-columns"><label>Situation<select id="scenarioSelect"><option value="restaurant">Restaurant</option><option value="hotel">Hotel check-in</option><option value="transport">Transportation</option><option value="shopping">Shopping</option><option value="medical">Medical</option><option value="emergency">Emergency</option></select></label><label>Destination<input id="packDestination" value="Malaysia" maxlength="120"></label><label>Phrase language<select id="assistantLanguage"><option value="en">English</option><option value="ms" selected>Bahasa Malaysia</option><option value="zh">Mandarin Chinese</option><option value="id">Indonesian</option><option value="th">Thai</option></select></label></div><div class="button-row"><button id="loadScenario" class="primary">Build my guide</button><button id="saveDestinationPack" class="secondary">Save offline pack</button></div><div id="packStatus" class="meta-box"></div><div id="scenarioSteps" class="scenario-steps"></div></article>
                    <article class="card-panel needs-card"><div class="card-kicker"><span>♥</span><div><h2>Needs & emergency card</h2><p>Keep essential information easy to show.</p></div></div><div class="form-grid two-columns"><label>Dietary requirement<input id="assistDietary" maxlength="500" placeholder="Vegetarian, halal…"></label><label>Allergies<input id="assistAllergy" maxlength="500" placeholder="Peanuts, medicine…"></label><label>Religious requirement<input id="assistReligious" maxlength="500" placeholder="No pork or alcohol…"></label><label>Spice level<select id="assistSpice"><option>Mild</option><option>Medium</option><option>Spicy</option><option>Not spicy</option></select></label></div><button id="prepareNeeds" class="secondary">Prepare restaurant request</button><div class="divider"></div><label>Emergency details<textarea id="assistEmergency" maxlength="500" rows="3" placeholder="Name, condition and a contact number"></textarea></label><button id="generateEmergency" class="danger">Generate bilingual emergency card</button><div id="culturalTips" class="meta-box"></div></article>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($user && $role === 'tourist'): ?>
            <section class="page" id="journey" data-title="My journey">
                <div class="page-heading"><div><span class="eyebrow">Personal travel space</span><h1>My journey</h1><p>Saved packs and suggestions shaped around your plans.</p></div><button type="button" class="secondary" data-page="profile">Edit travel preferences</button></div>
                <div class="journey-summary"><div><span>Preferred language</span><strong id="journeyLanguageSummary">—</strong></div><div><span>Next destination</span><strong id="journeyDestinationSummary">Not set</strong></div><div><span>Accessibility</span><strong id="journeyAccessibilitySummary">Personalised</strong></div></div>
                <div class="grid-two journey-grid"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Saved for offline</span><h2>Destination packs</h2></div></div><div id="destinationPacks" class="record-list"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Picked for you</span><h2>Recommended phrases</h2></div></div><div id="personalRecommendations" class="scenario-steps"></div></article></div>
            </section>
            <?php endif; ?>

            <?php if ($role === 'business' && !$approved): ?>
            <section class="page" id="application" data-title="Business application"><div class="page-heading"><div><span class="eyebrow">Partner with TourLingo</span><h1>Business application</h1><p id="applicationStatus">Application status: <?= htmlspecialchars((string)($businessStatus ?? 'pending')) ?></p></div><span class="status-pill pending">Under review</span></div><div class="narrow-content"><article class="card-panel application-card"><div class="card-kicker"><span>⌂</span><div><h2>Business details</h2><p>Keep this information accurate while our team reviews your application.</p></div></div><label>Business name<input id="applicationName" maxlength="160"></label><label>Category<input id="applicationCategory" maxlength="80"></label><label>Address<textarea id="applicationAddress" maxlength="500" rows="4"></textarea></label><button id="resubmitApplication" class="primary">Save and submit for review</button></article></div></section>
            <?php endif; ?>

            <?php if ($approved): ?>
            <section class="page" id="business" data-title="Business studio">
                <div class="page-heading"><div><span class="eyebrow">Business workspace</span><h1>Business studio</h1><p>Manage what travellers see without handling everything at once.</p></div><span class="status-pill approved">Approved business</span></div>
                <div class="module-tabs" role="tablist" aria-label="Business studio sections"><button class="active" type="button" data-business-tab="profile">Public profile</button><button type="button" data-business-tab="content">Content studio</button><button type="button" data-business-tab="library">Content library</button><button type="button" data-business-tab="reports">Visitor reports</button></div>
                <div class="module-view active" data-business-view="profile"><div class="business-profile-grid">
                    <article class="card-panel"><div class="card-kicker"><span>01</span><div><h2>Public service profile</h2><p>Choose a language and keep your visitor information clear.</p></div></div><label>Content language<select id="businessContentLanguage"><?= language_options() ?></select></label><div class="form-grid two-columns"><label>Name<input id="businessName" maxlength="160"></label><label>Category<input id="businessCategory" maxlength="80"></label></div><label>Address<textarea id="businessAddress" maxlength="500"></textarea></label><label>Description<textarea id="businessDescription" maxlength="3000"></textarea></label><label>Services<textarea id="businessServices" maxlength="3000"></textarea></label><div class="form-grid two-columns"><label>Payment methods<input id="businessPayments" maxlength="500"></label><label>Menu explanations<textarea id="businessMenu" maxlength="5000"></textarea></label></div><label>Facility explanations<textarea id="businessFacilities" maxlength="3000"></textarea></label><label class="switch-row"><span><strong>Public tourist page</strong><small>Allow travellers to open your shared business page.</small></span><input type="checkbox" id="businessPublic"></label><button id="saveBusiness" class="primary">Save this language</button></article>
                    <aside class="share-card"><span class="eyebrow light-eyebrow">Traveller access</span><h2>Share your business page</h2><p>Place this QR code where visitors can quickly find approved phrases and service details.</p><div id="businessQr" class="qr-box"></div><small id="qrStatus"></small><a id="publicBusinessLink" class="secondary full-button" target="_blank" rel="noopener">Open tourist page</a></aside>
                </div></div>
                <div class="module-view" data-business-view="content"><div class="content-studio-grid">
                    <article class="card-panel studio-card"><span class="studio-number">01</span><h2>Approved phrase</h2><p>Add a phrase travellers can use immediately.</p><label>Question or phrase<input id="phraseSource" maxlength="500"></label><label>Translation<input id="phraseTranslated" maxlength="500"></label><label>Suggested reply<input id="phraseReply" maxlength="500"></label><div class="form-grid two-columns"><label>Target language<select id="phraseTarget"><?= language_options() ?></select></label><label>Category<input id="phraseCategory" maxlength="80" value="General"></label></div><button id="addBusinessPhrase" class="primary">Add phrase</button></article>
                    <article class="card-panel studio-card"><span class="studio-number">02</span><h2>Frequently asked question</h2><p>Answer a common question once and publish it clearly.</p><label>Language<select id="faqLanguage"><?= language_options() ?></select></label><label>Question<input id="faqQuestion" maxlength="500"></label><label>Answer<textarea id="faqAnswer" maxlength="1000"></textarea></label><button id="addFaq" class="primary">Add FAQ</button></article>
                    <article class="card-panel studio-card"><span class="studio-number">03</span><h2>Local terminology</h2><p>Explain a Malaysian word or expression for visitors.</p><label>Language<select id="termLanguage"><?= language_options() ?></select></label><label>Term<input id="businessTerm" maxlength="120"></label><label>Explanation<input id="businessTermExplanation" maxlength="500"></label><button id="addBusinessTerm" class="primary">Save term</button></article>
                </div></div>
                <div class="module-view" data-business-view="library"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Published and draft items</span><h2>Content library</h2></div><button type="button" class="secondary" data-open-business-tab="content">Add new content</button></div><div id="businessContent" class="business-library"></div></article></div>
                <div class="module-view" data-business-view="reports"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Visitor communication</span><h2>Questions and usage</h2></div></div><div id="businessReports"></div></article></div>
            </section>
            <?php endif; ?>

            <?php if ($staff): ?>
            <section class="page" id="insights" data-title="Insights">
                <div class="page-heading"><div><span class="eyebrow">Communication intelligence</span><h1>Service insights</h1><p>Understand anonymous travel communication patterns at a glance.</p></div><a id="exportInsights" class="secondary">Export CSV</a></div>
                <div class="filter-panel"><div class="filter-row insight-filters"><label>From<input type="date" id="insightFrom"></label><label>To<input type="date" id="insightTo"></label><label>Language<select id="insightLanguage"><option value="">All</option><?= language_options() ?></select></label><label>Scenario<select id="insightScenario"><option value="">All</option><option value="restaurant">Restaurant</option><option value="hotel">Hotel</option><option value="transport">Transportation</option><option value="shopping">Shopping</option><option value="medical">Medical</option><option value="emergency">Emergency</option><option value="culture">Culture</option></select></label><label>Location<input id="insightLocation" maxlength="120" placeholder="All"></label><label>Business type<input id="insightBusinessType" maxlength="80" placeholder="All"></label><button id="filterInsights" class="primary">Apply filters</button></div></div>
                <div class="stat-grid"><article><span class="stat-icon aqua">文</span><strong id="insightTranslations">—</strong><small>Translations</small></article><article><span class="stat-icon coral">!</span><strong id="insightReports">—</strong><small>Needs attention</small></article><article><span class="stat-icon blue">⌂</span><strong id="insightBusinesses">—</strong><small>Businesses</small></article><article><span class="stat-icon gold">◷</span><strong id="insightPending">—</strong><small>Pending</small></article></div>
                <div class="grid-two mt-large"><article class="card-panel"><h2>Usage patterns</h2><div id="insightEvents"></div></article><article class="card-panel"><h2>Issues and repeated enquiries</h2><div id="insightIssues"></div><h3>Improvement recommendations</h3><div id="insightRecommendations"></div></article></div>
            </section>
            <?php endif; ?>

            <?php if ($role === 'admin' && $staff): ?>
            <section class="page" id="admin" data-title="Administration">
                <div class="page-heading"><div><span class="eyebrow">Platform operations</span><h1>Administration</h1><p>Review platform health, account activity and business access.</p></div><div class="report-actions"><span id="adminReportUpdated">Live report</span><div class="export-actions"><a id="exportAdminReport" class="secondary" href="api/admin.php?format=csv">Export CSV</a><a id="exportAdminPdf" class="secondary" href="api/admin.php?format=pdf">Export PDF</a></div></div></div>
                <div class="stat-grid"><article><span class="stat-icon aqua">◎</span><strong id="adminUsers">—</strong><small>Active users</small></article><article><span class="stat-icon blue">⌂</span><strong id="adminBusinesses">—</strong><small>Approved businesses</small></article><article><span class="stat-icon gold">◷</span><strong id="adminPending">—</strong><small>Pending review</small></article><article><span class="stat-icon coral">文</span><strong id="adminTranslations">—</strong><small>Translations</small></article></div>
                <div class="admin-report-grid mt-large"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Accounts</span><h2>Users by role</h2></div></div><div id="adminRoleReport"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Business network</span><h2>Review status</h2></div></div><div id="adminBusinessReport"></div></article></div>
                <div class="admin-operations-grid mt-large"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Review queue</span><h2>Pending business registrations</h2></div></div><div id="pendingBusinesses"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Audit trail</span><h2>Recent activity</h2></div></div><div id="adminActivity"></div></article></div>
            </section>
            <?php endif; ?>

            <?php if ($user): ?>
            <section class="page" id="profile" data-title="Profile & settings">
                <div class="page-heading"><div><span class="eyebrow">Your TourLingo account</span><h1>Profile & settings</h1><p>Keep your account, preferences and security details in one place.</p></div></div>
                <div class="profile-layout">
                    <aside class="profile-summary-card"><span class="profile-avatar-large" id="profileAvatar"><?php if ($profileImage !== ''): ?><img src="<?= htmlspecialchars($profileImage) ?>" alt="<?= htmlspecialchars($name) ?> profile picture"><?php else: ?><span><?= htmlspecialchars($profileInitial) ?></span><?php endif; ?></span><h2 id="profileSummaryName"><?= htmlspecialchars($name) ?></h2><p id="profileSummaryEmail"><?= htmlspecialchars((string)$user['email']) ?></p><span class="role-badge"><?= htmlspecialchars(ucfirst($role)) ?></span><div class="profile-summary-list"><div><span>Account status</span><strong><?= htmlspecialchars(ucfirst((string)$user['status'])) ?></strong></div><div><span>Member type</span><strong>TourLingo <?= htmlspecialchars(ucfirst($role)) ?></strong></div></div></aside>
                    <div class="profile-content">
                        <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Account</span><h2>Personal details</h2></div></div><div class="profile-photo-editor"><span class="profile-photo-preview" id="profilePhotoPreview"><?php if ($profileImage !== ''): ?><img src="<?= htmlspecialchars($profileImage) ?>" alt="Selected profile picture"><?php else: ?><span><?= htmlspecialchars($profileInitial) ?></span><?php endif; ?></span><div class="profile-photo-copy"><strong>Profile picture</strong><p>Upload a JPG, PNG or WebP image up to 2 MB.</p><div class="upload-actions"><label class="secondary file-picker" for="profilePictureInput">Choose image</label><input id="profilePictureInput" type="file" accept="image/jpeg,image/png,image/webp" hidden><button id="uploadProfilePicture" class="primary" type="button" disabled>Upload picture</button></div><small id="profilePictureName">No new image selected.</small></div></div><div class="form-grid two-columns"><label>Full name<input id="accountFullName" maxlength="120" value="<?= htmlspecialchars($name) ?>"></label><label>Email address<input id="accountEmail" type="email" maxlength="190" value="<?= htmlspecialchars((string)$user['email']) ?>"></label></div><label>Preferred language<select id="accountLanguage"><?php foreach (['en'=>'English','ms'=>'Bahasa Malaysia','zh'=>'Mandarin Chinese','id'=>'Indonesian','th'=>'Thai'] as $code=>$label): ?><option value="<?= $code ?>" <?= ($user['preferred_language'] ?? 'en') === $code ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button id="saveAccountProfile" class="primary">Save account details</button></article>
                        <?php if ($role === 'tourist'): ?>
                        <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Travel preferences</span><h2>Make TourLingo work for you</h2></div></div><div class="form-grid two-columns"><label>Profile name<input id="profileName" maxlength="120"></label><label>Travel language<select id="profileLanguage"><?= language_options() ?></select></label><label>Default destination<input id="profileDestination" maxlength="120" placeholder="Where are you going next?"></label><label>Emergency contact<input id="profileEmergencyContact" maxlength="120"></label></div><div class="form-grid two-columns"><label>Accessibility settings<textarea id="profileAccessibility" maxlength="500"></textarea></label><label>Dietary preferences<textarea id="profileDietary" maxlength="500"></textarea></label><label>Allergies<textarea id="profileAllergy" maxlength="500"></textarea></label><label>Optional emergency details<textarea id="profileEmergencyDetails" maxlength="500"></textarea></label></div><div class="preference-row"><label class="switch-row"><span><strong>Large text</strong><small>Increase text and control sizes.</small></span><input type="checkbox" id="profileLargeText"></label><label class="switch-row"><span><strong>Automatic voice playback</strong><small>Read successful translations aloud.</small></span><input type="checkbox" id="profileVoice"></label></div><button id="saveProfile" class="primary">Save travel preferences</button></article>
                        <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Privacy</span><h2>Data and consent</h2></div></div><label class="switch-row"><span><strong>Save translation history</strong><small>Keep your recent translations available.</small></span><input type="checkbox" id="historyConsent" checked></label><label class="switch-row"><span><strong>Share anonymous usage data</strong><small>Help improve common travel communication.</small></span><input type="checkbox" id="analyticsConsent"></label><button id="deleteJourneyData" class="danger ghost-danger">Delete saved journey data</button></article>
                        <?php endif; ?>
                        <article class="card-panel security-card"><div class="card-title-row"><div><span class="eyebrow">Security</span><h2>Change password</h2></div></div><div class="form-grid two-columns"><label>Current password<input id="currentPassword" type="password" autocomplete="current-password"></label><label>New password<input id="newPassword" type="password" minlength="8" autocomplete="new-password"></label></div><label>Confirm new password<input id="confirmNewPassword" type="password" minlength="8" autocomplete="new-password"></label><button id="changePassword" class="secondary">Update password</button></article>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <footer>© <?= date('Y') ?> TourLingo · Multilingual travel communication for Malaysia</footer>
        </div>
    </main>
</div>
<div id="toast" class="toast-message" role="status" aria-live="polite"></div>
<div id="messageOverlay" class="emergency-overlay" aria-hidden="true"><button id="closeOverlay" aria-label="Close message">×</button><strong id="overlaySource"></strong><strong id="overlayTranslation"></strong><span id="overlayExtra"></span></div>
<script>window.JOM={csrf:<?= json_encode(csrf_token()) ?>,authenticated:<?= $user ? 'true' : 'false' ?>,role:<?= json_encode($role) ?>};</script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>if(!window.QRCode){document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"><\/script>');}</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/app.js?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/assets/js/app.js') ?: '1')) ?>"></script>
</body>
</html>
