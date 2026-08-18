<?php
declare(strict_types=1);
$year = date('Y');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="JomCommunicate multilingual tourism communication platform">
  <title>JomCommunicate</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="#communication" data-page-link="communication">
      <span class="brand-mark">JC</span><span>JomCommunicate<small>Travel without language barriers</small></span>
    </a>
    <nav class="side-nav">
      <button class="nav-link active" data-page="communication"><span>💬</span>Communication</button>
      <button class="nav-link" data-page="assistance"><span>🧭</span>Smart assistance</button>
      <button class="nav-link" data-page="business"><span>🏪</span>Business portal</button>
      <button class="nav-link" data-page="journey"><span>🧳</span>My journey</button>
      <button class="nav-link" data-page="insights"><span>📊</span>Insights</button>
      <button class="nav-link" data-page="admin"><span>⚙️</span>Administration</button>
    </nav>
    <div class="sidebar-foot">
      <span class="status-dot"></span> Demo mode ready
      <small>HTML · CSS · JavaScript · PHP · MySQL</small>
    </div>
  </aside>

  <main class="main-area">
    <header class="topbar">
      <button class="menu-button" id="menuButton" aria-label="Open navigation">☰</button>
      <div><strong>Malaysia visitor support</strong><small id="todayLabel"></small></div>
      <div class="top-actions">
        <button class="icon-button" id="contrastButton" title="Toggle high contrast">◐</button>
        <button class="profile-button"><span>AL</span><span>Aina Lee<small>Tourist</small></span></button>
      </div>
    </header>

    <div class="content-wrap">
      <section class="page active" id="communication">
        <div class="page-heading"><div><span class="eyebrow">Live language support</span><h1>Communicate with confidence</h1><p>Translate common travel conversations and save useful phrases for later.</p></div><span class="pill">Works with browser speech</span></div>
        <div class="tab-row" role="tablist">
          <button class="tab active" data-tab-group="communication" data-tab="textTranslation">Text translation</button>
          <button class="tab" data-tab-group="communication" data-tab="voiceConversation">Voice conversation</button>
          <button class="tab" data-tab-group="communication" data-tab="localGlossary">Local glossary</button>
        </div>

        <div class="tab-panel active" id="textTranslation">
          <div class="grid-two">
            <article class="card-panel">
              <div class="card-title"><div><span class="icon-tile">文</span><h2>Translate a message</h2></div><button class="text-button" id="swapLanguages">⇄ Swap</button></div>
              <div class="row g-3">
                <div class="col-md-6"><label for="sourceLanguage">From</label><select id="sourceLanguage" class="form-select"><option value="en">English</option><option value="ms">Bahasa Melayu</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></div>
                <div class="col-md-6"><label for="targetLanguage">To</label><select id="targetLanguage" class="form-select"><option value="ms">Bahasa Melayu</option><option value="en">English</option><option value="zh">Mandarin</option><option value="ta">Tamil</option></select></div>
              </div>
              <label for="sourceText">Your message</label>
              <textarea id="sourceText" class="form-control" rows="5" maxlength="500" placeholder="Example: Where is the nearest train station?">Where is the nearest train station?</textarea>
              <div class="field-footer"><button class="text-button" id="listenInput">🎙 Speak</button><span><span id="characterCount">39</span>/500</span></div>
              <button class="btn-primary-custom" id="translateButton">Translate message</button>
            </article>
            <article class="card-panel result-panel">
              <div class="card-title"><div><span class="icon-tile pale">✓</span><h2>Translation</h2></div><span class="confidence">Demo dictionary</span></div>
              <div class="translation-result" id="translationResult" aria-live="polite"><span>Stesen kereta api terdekat di mana?</span></div>
              <div class="button-row"><button class="btn-secondary-custom" id="speakResult">🔊 Listen</button><button class="btn-secondary-custom" id="copyResult">⧉ Copy</button><button class="btn-primary-custom compact" id="savePhrase">＋ Save phrase</button></div>
              <div class="tip"><strong>Local tip</strong><p>“Di mana” means “where” in Bahasa Melayu. A friendly “terima kasih” means thank you.</p></div>
            </article>
          </div>
          <article class="card-panel mt-4"><div class="card-title"><div><span class="icon-tile">↺</span><h2>Recent translations</h2></div><button class="text-button" data-page-target="journey">View saved phrases</button></div><div id="recentTranslations" class="record-list"></div></article>
        </div>

        <div class="tab-panel" id="voiceConversation">
          <article class="card-panel conversation-card"><span class="eyebrow">Two-way voice mode</span><h2>Pass the phone between speakers</h2><p>Tap a microphone, speak, then let the other person listen to the translated message.</p><div class="speaker-grid"><div><span class="speaker-avatar">A</span><h3>Traveller · English</h3><button class="mic-button" data-listen-lang="en-US">🎙 Hold to speak</button></div><div class="conversation-arrow">⇄</div><div><span class="speaker-avatar local">B</span><h3>Local · Bahasa Melayu</h3><button class="mic-button" data-listen-lang="ms-MY">🎙 Hold to speak</button></div></div><div id="voiceTranscript" class="transcript">Your conversation transcript will appear here.</div></article>
        </div>

        <div class="tab-panel" id="localGlossary">
          <div class="glossary-grid" id="glossaryGrid"></div>
        </div>
      </section>

      <section class="page" id="assistance">
        <div class="page-heading"><div><span class="eyebrow">Smart assistance</span><h1>Help for real travel moments</h1><p>Use guided phrases for food, transport, emergencies and accessibility.</p></div></div>
        <div class="stat-grid"><article class="stat-card"><span>🧭</span><strong>8</strong><small>Guided scenarios</small></article><article class="stat-card"><span>🥗</span><strong>12</strong><small>Dietary phrases</small></article><article class="stat-card"><span>🛟</span><strong>24/7</strong><small>Emergency card</small></article><article class="stat-card"><span>⬇</span><strong>3</strong><small>Offline packs</small></article></div>
        <div class="grid-two mt-4">
          <article class="card-panel"><div class="card-title"><div><span class="icon-tile">🗣</span><h2>Guided scenario</h2></div></div><label for="scenarioSelect">I need help with</label><select class="form-select" id="scenarioSelect"><option value="restaurant">Ordering at a restaurant</option><option value="transport">Taking public transport</option><option value="hotel">Checking into a hotel</option><option value="shopping">Shopping and prices</option></select><div id="scenarioSteps" class="scenario-steps"></div></article>
          <article class="card-panel emergency-card"><div class="card-title"><div><span class="icon-tile danger">SOS</span><h2>Emergency card</h2></div></div><p>Show this card to someone nearby. It includes your key information in English and Bahasa Melayu.</p><div class="emergency-message"><strong>I need help / Saya perlukan bantuan</strong><span>Emergency contact: +60 12-345 6789</span><span>Allergy: Peanuts / Alahan: Kacang tanah</span></div><button class="btn-danger-custom" id="fullscreenEmergency">Open large emergency card</button></article>
        </div>
        <article class="card-panel mt-4"><div class="card-title"><div><span class="icon-tile">⬇</span><h2>Offline language packs</h2></div></div><div class="pack-list"><div><div><strong>Kuala Lumpur essentials</strong><small>Transport, food and emergency phrases</small></div><button class="btn-secondary-custom pack-button">Download</button></div><div><div><strong>Penang food guide</strong><small>Hawker food names and dietary phrases</small></div><button class="btn-secondary-custom pack-button">Download</button></div><div><div><strong>Sabah adventure pack</strong><small>Nature, directions and safety phrases</small></div><button class="btn-secondary-custom pack-button">Download</button></div></div></article>
      </section>

      <section class="page" id="business">
        <div class="page-heading"><div><span class="eyebrow">Business portal</span><h1>Welcome back, Warung Harmoni</h1><p>Keep your multilingual visitor information accurate and easy to access.</p></div><button class="btn-primary-custom compact" id="saveBusiness">Save changes</button></div>
        <div class="stat-grid"><article class="stat-card"><span>👁</span><strong>1,248</strong><small>Profile views</small></article><article class="stat-card"><span>⌁</span><strong>486</strong><small>QR scans</small></article><article class="stat-card"><span>💬</span><strong>73</strong><small>Phrase uses</small></article><article class="stat-card"><span>★</span><strong>4.8</strong><small>Helpfulness</small></article></div>
        <div class="grid-two mt-4"><article class="card-panel"><h2>Business profile</h2><label for="businessName">Business name</label><input class="form-control" id="businessName" value="Warung Harmoni"><label for="businessCategory">Category</label><select class="form-select" id="businessCategory"><option>Food & drink</option><option>Accommodation</option><option>Attraction</option><option>Transport</option></select><label for="businessAddress">Address</label><textarea class="form-control" id="businessAddress" rows="3">24 Jalan Alor, Bukit Bintang, Kuala Lumpur</textarea></article><article class="card-panel qr-card"><h2>Visitor QR access</h2><div id="qrCode" class="qr-placeholder" aria-label="QR code"><span>JC</span></div><p>Place this QR code at your counter so visitors can open your translated information.</p><button class="btn-secondary-custom" id="downloadQr">Download QR poster</button></article></div>
        <article class="card-panel mt-4"><div class="card-title"><div><span class="icon-tile">文</span><h2>Frequently used phrases</h2></div><button class="text-button" id="addBusinessPhrase">＋ Add phrase</button></div><div class="table-responsive"><table class="data-table"><thead><tr><th>English</th><th>Bahasa Melayu</th><th>Category</th><th>Status</th></tr></thead><tbody id="businessPhraseRows"><tr><td>Please order at the counter</td><td>Sila pesan di kaunter</td><td>Service</td><td><span class="badge-good">Published</span></td></tr><tr><td>This dish contains peanuts</td><td>Hidangan ini mengandungi kacang tanah</td><td>Dietary</td><td><span class="badge-good">Published</span></td></tr></tbody></table></div></article>
      </section>

      <section class="page" id="journey">
        <div class="page-heading"><div><span class="eyebrow">My journey</span><h1>Your travel language kit</h1><p>Keep useful phrases, access preferences and destination packs together.</p></div></div>
        <div class="grid-two"><article class="card-panel"><h2>Traveller profile</h2><div class="profile-summary"><span>AL</span><div><strong>Aina Lee</strong><small>English speaker · Visiting Malaysia</small></div></div><label for="preferredLanguage">Preferred local language</label><select id="preferredLanguage" class="form-select"><option>Bahasa Melayu</option><option>Mandarin</option><option>Tamil</option></select><label class="check-row"><input type="checkbox" checked> Use large text when possible</label><label class="check-row"><input type="checkbox"> Prefer voice playback</label><label class="check-row"><input type="checkbox" checked> Show dietary alerts</label></article><article class="card-panel"><div class="card-title"><div><span class="icon-tile">★</span><h2>Saved phrases</h2></div><span class="pill" id="savedCount">0 saved</span></div><div id="savedPhrases" class="record-list"></div></article></div>
        <article class="card-panel mt-4 privacy-panel"><div><span class="icon-tile">🔒</span><div><h2>Privacy choices</h2><p>Your conversation text stays on this device unless you choose to save it.</p></div></div><label class="switch-row"><span>Share anonymous usage data<small>Helps improve commonly requested phrases.</small></span><input type="checkbox" id="analyticsConsent"></label><label class="switch-row"><span>Save translation history<small>Stores translations in MySQL or local browser storage.</small></span><input type="checkbox" id="historyConsent" checked></label></article>
      </section>

      <section class="page" id="insights">
        <div class="page-heading"><div><span class="eyebrow">Service insights</span><h1>Understand visitor communication needs</h1><p>Demo analytics help tourism teams identify language gaps and peak demand.</p></div><select class="form-select period-select" id="periodSelect"><option>Last 30 days</option><option>Last 7 days</option><option>This year</option></select></div>
        <div class="stat-grid"><article class="stat-card"><span>文</span><strong>8,420</strong><small>Translations</small></article><article class="stat-card"><span>🎙</span><strong>2,136</strong><small>Voice sessions</small></article><article class="stat-card"><span>✓</span><strong>92%</strong><small>Helpful results</small></article><article class="stat-card"><span>↗</span><strong>+18%</strong><small>Monthly growth</small></article></div>
        <div class="grid-two mt-4"><article class="card-panel"><h2>Most requested scenarios</h2><div class="bar-list"><div><span>Food & dining <b>34%</b></span><i style="--value:34%"></i></div><div><span>Transport <b>27%</b></span><i style="--value:27%"></i></div><div><span>Accommodation <b>19%</b></span><i style="--value:19%"></i></div><div><span>Shopping <b>12%</b></span><i style="--value:12%"></i></div><div><span>Emergency <b>8%</b></span><i style="--value:8%"></i></div></div></article><article class="card-panel"><h2>Language demand</h2><div class="donut-wrap"><div class="donut"><span>8.4k<small>total</small></span></div><ul><li><i class="dot green"></i>Bahasa Melayu <b>46%</b></li><li><i class="dot teal"></i>Mandarin <b>28%</b></li><li><i class="dot gold"></i>Tamil <b>16%</b></li><li><i class="dot gray"></i>Other <b>10%</b></li></ul></div></article></div>
        <article class="card-panel mt-4"><div class="card-title"><div><span class="icon-tile">✦</span><h2>Recommendations</h2></div></div><div class="recommendation-grid"><div><strong>Add late-night transport phrases</strong><p>Requests increase after 10 PM around Bukit Bintang.</p></div><div><strong>Review allergy translations</strong><p>Peanut and shellfish questions are the fastest-growing dietary requests.</p></div><div><strong>Expand Mandarin voice prompts</strong><p>Voice use is 22% higher than text for Mandarin-speaking visitors.</p></div></div></article>
      </section>

      <section class="page" id="admin">
        <div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Platform operations</h1><p>Manage registrations, content quality, privacy rules and system health.</p></div><span class="pill good">All systems operational</span></div>
        <div class="stat-grid"><article class="stat-card"><span>👥</span><strong>4,892</strong><small>Active users</small></article><article class="stat-card"><span>🏪</span><strong>326</strong><small>Businesses</small></article><article class="stat-card"><span>⏳</span><strong>14</strong><small>Pending reviews</small></article><article class="stat-card"><span>✓</span><strong>99.9%</strong><small>Service uptime</small></article></div>
        <div class="grid-two mt-4"><article class="card-panel"><div class="card-title"><div><span class="icon-tile">⌛</span><h2>Pending registrations</h2></div><span class="pill">3 new</span></div><div class="approval-list"><div><span class="avatar-small">NS</span><div><strong>Nasi Seni</strong><small>Restaurant · Kuala Lumpur</small></div><button class="approve-button">Approve</button></div><div><span class="avatar-small">BB</span><div><strong>Borneo Breeze</strong><small>Tour operator · Sabah</small></div><button class="approve-button">Approve</button></div><div><span class="avatar-small">PH</span><div><strong>Penang Heritage Inn</strong><small>Accommodation · Penang</small></div><button class="approve-button">Approve</button></div></div></article><article class="card-panel"><h2>System health</h2><div class="health-list"><div><span><i class="status-dot"></i>PHP application</span><b>Online</b></div><div><span><i class="status-dot"></i>MySQL database</span><b id="databaseStatus">Checking…</b></div><div><span><i class="status-dot"></i>Speech service</span><b id="speechStatus">Checking…</b></div><div><span><i class="status-dot"></i>Local storage fallback</span><b>Ready</b></div></div></article></div>
        <article class="card-panel mt-4"><div class="card-title"><div><span class="icon-tile">☷</span><h2>Recent audit activity</h2></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Action</th><th>User</th><th>Area</th><th>Time</th></tr></thead><tbody><tr><td>Business registration approved</td><td>admin@jom.my</td><td>Registrations</td><td>10 min ago</td></tr><tr><td>Dietary phrase updated</td><td>editor@jom.my</td><td>Content</td><td>42 min ago</td></tr><tr><td>Privacy consent exported</td><td>dpo@jom.my</td><td>Privacy</td><td>2 hr ago</td></tr></tbody></table></div></article>
      </section>

      <footer>© <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?> JomCommunicate · Student collaborative tourism project</footer>
    </div>
  </main>
</div>

<div class="toast-message" id="toast" role="status" aria-live="polite"></div>
<div class="emergency-overlay" id="emergencyOverlay" aria-hidden="true"><button id="closeEmergency" aria-label="Close emergency card">×</button><strong>I NEED HELP</strong><strong>SAYA PERLUKAN BANTUAN</strong><span>Call 999 · Emergency contact +60 12-345 6789</span></div>

<script src="assets/js/app.js"></script>
</body>
</html>
