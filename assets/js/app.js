(function () {
  'use strict';

  var storageKey = 'jomcommunicate_records';
  var records = [];
  var currentTranslation = 'Stesen kereta api terdekat di mana?';
  var speechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  var dictionary = {
    'en-ms': {
      'where is the nearest train station?': 'Stesen kereta api terdekat di mana?',
      'how much is this?': 'Berapakah harga ini?',
      'i need help': 'Saya perlukan bantuan',
      'does this contain peanuts?': 'Adakah ini mengandungi kacang tanah?',
      'thank you': 'Terima kasih'
    },
    'ms-en': {
      'stesen kereta api terdekat di mana?': 'Where is the nearest train station?',
      'berapakah harga ini?': 'How much is this?',
      'saya perlukan bantuan': 'I need help',
      'terima kasih': 'Thank you'
    },
    'en-zh': {'thank you': '谢谢', 'where is the nearest train station?': '最近的火车站在哪里？'},
    'en-ta': {'thank you': 'நன்றி', 'where is the nearest train station?': 'அருகிலுள்ள ரயில் நிலையம் எங்கே?'}
  };

  var glossary = [
    ['Hello', 'Hai / Apa khabar', 'Greeting'], ['Thank you', 'Terima kasih', 'Greeting'],
    ['How much?', 'Berapa harga?', 'Shopping'], ['Where is…?', 'Di mana…?', 'Directions'],
    ['No spicy food', 'Jangan pedas', 'Food'], ['I am vegetarian', 'Saya vegetarian', 'Dietary'],
    ['Please help me', 'Tolong saya', 'Emergency'], ['Train station', 'Stesen kereta api', 'Transport'],
    ['Turn left', 'Belok kiri', 'Directions']
  ];

  var scenarios = {
    restaurant: [['Ask for a table', 'Meja untuk dua orang, sila.'], ['Mention an allergy', 'Saya alah kepada kacang tanah.'], ['Ask for the bill', 'Boleh saya minta bil?']],
    transport: [['Name your destination', 'Saya mahu pergi ke KL Sentral.'], ['Ask the fare', 'Berapakah tambangnya?'], ['Confirm the stop', 'Adakah ini perhentian saya?']],
    hotel: [['Give your booking name', 'Tempahan atas nama Aina Lee.'], ['Ask about check-in', 'Boleh saya daftar masuk sekarang?'], ['Request Wi-Fi', 'Apakah kata laluan Wi-Fi?']],
    shopping: [['Ask the price', 'Berapakah harga ini?'], ['Ask for another size', 'Ada saiz lain?'], ['Pay by card', 'Boleh bayar dengan kad?']]
  };

  function $(selector) { return document.querySelector(selector); }
  function $all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }
  function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value; return node.innerHTML; }
  function toast(message) { var element = $('#toast'); element.textContent = message; element.classList.add('show'); window.setTimeout(function () { element.classList.remove('show'); }, 2200); }

  function showPage(id) {
    $all('.page').forEach(function (page) { page.classList.toggle('active', page.id === id); });
    $all('[data-page]').forEach(function (button) { button.classList.toggle('active', button.dataset.page === id); });
    $('.sidebar').classList.remove('open');
    window.location.hash = id;
    window.scrollTo({top: 0, behavior: 'smooth'});
  }

  function showTab(group, id) {
    $all('[data-tab-group="' + group + '"]').forEach(function (tab) { tab.classList.toggle('active', tab.dataset.tab === id); });
    $('#' + group).querySelectorAll('.tab-panel').forEach(function (panel) { panel.classList.toggle('active', panel.id === id); });
  }

  function translate() {
    var source = $('#sourceText').value.trim();
    if (!source) { toast('Enter a message first.'); return; }
    var key = $('#sourceLanguage').value + '-' + $('#targetLanguage').value;
    var translated = (dictionary[key] || {})[source.toLowerCase()];
    if (!translated) {
      translated = '[' + $('#targetLanguage').selectedOptions[0].text + '] ' + source;
      toast('Demo dictionary used. Connect Google Translation API for full translations.');
    }
    currentTranslation = translated;
    $('#translationResult').innerHTML = '<span>' + escapeHtml(translated) + '</span>';
  }

  async function loadRecords() {
    try {
      var response = await fetch('api/records.php', {headers: {'Accept': 'application/json'}});
      if (!response.ok) { throw new Error('Database unavailable'); }
      var data = await response.json();
      records = data.records || [];
      $('#databaseStatus').textContent = 'Connected';
    } catch (error) {
      records = JSON.parse(localStorage.getItem(storageKey) || '[]');
      $('#databaseStatus').textContent = 'Local fallback';
    }
    renderRecords();
  }

  async function saveRecord(record) {
    if (!$('#historyConsent').checked && (record.record_type === 'translation' || record.record_type === 'phrase')) {
      toast('Enable translation history in Privacy choices to save.'); return;
    }
    try {
      var response = await fetch('api/records.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(record)});
      if (!response.ok) { throw new Error('Database unavailable'); }
      var data = await response.json(); record.id = data.id;
    } catch (error) {
      record.id = 'local-' + Date.now();
      record.created_at = new Date().toISOString();
      var stored = JSON.parse(localStorage.getItem(storageKey) || '[]'); stored.unshift(record);
      localStorage.setItem(storageKey, JSON.stringify(stored.slice(0, 100)));
    }
    records.unshift(record); renderRecords(); toast('Saved successfully.');
  }

  async function deleteRecord(id) {
    if (String(id).indexOf('local-') === 0) {
      var stored = JSON.parse(localStorage.getItem(storageKey) || '[]').filter(function (record) { return record.id !== id; });
      localStorage.setItem(storageKey, JSON.stringify(stored));
    } else {
      try { await fetch('api/records.php?id=' + encodeURIComponent(id), {method: 'DELETE'}); } catch (error) {}
    }
    records = records.filter(function (record) { return String(record.id) !== String(id); }); renderRecords();
  }

  function renderRecords() {
    var translations = records.filter(function (record) { return record.record_type === 'translation'; }).slice(0, 5);
    var phrases = records.filter(function (record) { return record.record_type === 'phrase'; });
    $('#recentTranslations').innerHTML = translations.length ? translations.map(recordHtml).join('') : '<div class="empty-state">No translation history yet.</div>';
    $('#savedPhrases').innerHTML = phrases.length ? phrases.map(recordHtml).join('') : '<div class="empty-state">Save a translation to build your phrase list.</div>';
    $('#savedCount').textContent = phrases.length + ' saved';
    $all('[data-delete-record]').forEach(function (button) { button.onclick = function () { deleteRecord(button.dataset.deleteRecord); }; });
  }

  function recordHtml(record) {
    return '<div class="record-item"><div><strong>' + escapeHtml(record.title) + '</strong><small>' + escapeHtml(record.content) + '</small></div><button data-delete-record="' + escapeHtml(String(record.id)) + '" aria-label="Delete">×</button></div>';
  }

  function speak(text, lang) {
    if (!('speechSynthesis' in window)) { toast('Speech playback is not supported by this browser.'); return; }
    var utterance = new SpeechSynthesisUtterance(text); utterance.lang = lang || 'ms-MY'; speechSynthesis.cancel(); speechSynthesis.speak(utterance);
  }

  function listen(language, callback) {
    if (!speechRecognition) { toast('Voice recognition works best in Chrome or Edge.'); return; }
    var recognition = new speechRecognition(); recognition.lang = language; recognition.interimResults = false;
    recognition.onstart = function () { toast('Listening…'); };
    recognition.onerror = function () { toast('Could not hear you. Please try again.'); };
    recognition.onresult = function (event) { callback(event.results[0][0].transcript); };
    recognition.start();
  }

  function renderGlossary() {
    $('#glossaryGrid').innerHTML = glossary.map(function (item) { return '<article class="glossary-card"><small>' + item[2] + '</small><strong>' + item[0] + '</strong><p>' + item[1] + '</p><button data-glossary-speak="' + escapeHtml(item[1]) + '">🔊 Listen</button></article>'; }).join('');
    $all('[data-glossary-speak]').forEach(function (button) { button.onclick = function () { speak(button.dataset.glossarySpeak, 'ms-MY'); }; });
  }

  function renderScenario() {
    $('#scenarioSteps').innerHTML = scenarios[$('#scenarioSelect').value].map(function (step, index) { return '<div class="scenario-step"><strong>' + (index + 1) + '. ' + step[0] + '</strong><span>' + step[1] + '</span></div>'; }).join('');
  }

  $all('[data-page]').forEach(function (button) { button.addEventListener('click', function () { showPage(button.dataset.page); }); });
  $all('[data-page-link]').forEach(function (link) { link.addEventListener('click', function (event) { event.preventDefault(); showPage(link.dataset.pageLink); }); });
  $all('[data-page-target]').forEach(function (button) { button.addEventListener('click', function () { showPage(button.dataset.pageTarget); }); });
  $all('[data-tab]').forEach(function (button) { button.addEventListener('click', function () { showTab(button.dataset.tabGroup, button.dataset.tab); }); });
  $('#menuButton').onclick = function () { $('.sidebar').classList.toggle('open'); };
  $('#contrastButton').onclick = function () { document.body.classList.toggle('high-contrast'); };
  $('#sourceText').oninput = function () { $('#characterCount').textContent = $('#sourceText').value.length; };
  $('#translateButton').onclick = translate;
  $('#swapLanguages').onclick = function () { var source = $('#sourceLanguage').value; $('#sourceLanguage').value = $('#targetLanguage').value; $('#targetLanguage').value = source; };
  $('#listenInput').onclick = function () { listen($('#sourceLanguage').value === 'ms' ? 'ms-MY' : 'en-US', function (text) { $('#sourceText').value = text; $('#characterCount').textContent = text.length; translate(); }); };
  $('#speakResult').onclick = function () { speak(currentTranslation, $('#targetLanguage').value === 'ms' ? 'ms-MY' : $('#targetLanguage').value); };
  $('#copyResult').onclick = function () { navigator.clipboard.writeText(currentTranslation).then(function () { toast('Translation copied.'); }); };
  $('#savePhrase').onclick = function () { saveRecord({record_type: 'phrase', title: $('#sourceText').value.trim(), content: currentTranslation, metadata: {from: $('#sourceLanguage').value, to: $('#targetLanguage').value}}); };
  $all('.mic-button').forEach(function (button) { button.onclick = function () { listen(button.dataset.listenLang, function (text) { $('#voiceTranscript').textContent = text; }); }; });
  $('#scenarioSelect').onchange = renderScenario;
  $('#fullscreenEmergency').onclick = function () { $('#emergencyOverlay').classList.add('open'); $('#emergencyOverlay').setAttribute('aria-hidden', 'false'); };
  $('#closeEmergency').onclick = function () { $('#emergencyOverlay').classList.remove('open'); $('#emergencyOverlay').setAttribute('aria-hidden', 'true'); };
  $all('.pack-button').forEach(function (button) { button.onclick = function () { button.textContent = 'Downloaded ✓'; button.disabled = true; toast('Demo language pack saved for offline use.'); }; });
  $('#saveBusiness').onclick = function () { saveRecord({record_type: 'business', title: $('#businessName').value, content: $('#businessAddress').value, metadata: {category: $('#businessCategory').value}}); };
  $('#downloadQr').onclick = function () { window.print(); };
  $('#addBusinessPhrase').onclick = function () { var english = window.prompt('English phrase:'); if (!english) return; var malay = window.prompt('Bahasa Melayu translation:'); if (!malay) return; $('#businessPhraseRows').insertAdjacentHTML('beforeend', '<tr><td>' + escapeHtml(english) + '</td><td>' + escapeHtml(malay) + '</td><td>Custom</td><td><span class="badge-good">Published</span></td></tr>'); };
  $all('.approve-button').forEach(function (button) { button.onclick = function () { button.textContent = 'Approved ✓'; button.disabled = true; }; });

  $('#todayLabel').textContent = new Intl.DateTimeFormat('en-MY', {weekday: 'long', day: 'numeric', month: 'long'}).format(new Date());
  $('#speechStatus').textContent = speechRecognition ? 'Available' : 'Playback only';
  renderGlossary(); renderScenario(); loadRecords();
  var startPage = window.location.hash.slice(1);
  if (startPage) {
    var requestedPage = document.getElementById(startPage);
    if (requestedPage && requestedPage.classList.contains('page')) { showPage(startPage); }
  }
}());
