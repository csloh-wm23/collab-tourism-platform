<?php
declare(strict_types=1);
$slug = trim((string) ($_GET['slug'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="icon" href="assets/logo.svg" type="image/svg+xml">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Traveller-friendly business information and approved multilingual phrases from TourLingo.">
    <title>Tourism business · TourLingo</title>
    <script>try{if(localStorage.getItem('jomcommunicate_theme')==='dark')document.documentElement.classList.add('dark-mode');}catch(error){}</script>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= rawurlencode(
        (string) (filemtime(__DIR__ . '/assets/css/styles.css') ?: '1'),
    ) ?>">
</head>
<body class="public-business">
    <header class="public-topbar">
        <a class="brand public-brand" href="index.php"><span class="brand-mark"><img src="assets/logo.svg" width="46" height="46" alt=""></span><span>TourLingo<small>Travel with confidence</small></span></a>
        <div class="public-topbar-actions">
            <button id="publicContrastButton" class="icon-button public-contrast-button" type="button" aria-label="Enable dark mode" aria-pressed="false" title="Enable dark mode">☾</button>
            <a class="secondary" href="index.php">Open travel tools</a>
        </div>
    </header>
    <main class="content-wrap public-wrap">
        <div id="publicStatus" class="card-panel public-loading"><span class="action-icon aqua">⌂</span><div><span class="eyebrow">TourLingo business</span><h1>Loading business information…</h1><p>Please wait while we prepare the traveller page.</p></div></div>
        <section id="publicBusiness" hidden>
            <div class="public-hero">
                <div><span class="eyebrow light-eyebrow">Verified tourism business</span><h1 id="publicName"></h1><p id="publicDescription"></p></div>
                <label>Your language<select id="publicLanguage"><option value="ms">Bahasa Malaysia</option><option value="en">English</option><option value="zh">Mandarin Chinese</option><option value="id">Indonesian</option><option value="th">Thai</option></select></label>
            </div>
            <div class="grid-two public-primary-grid">
                <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Before you visit</span><h2>Service information</h2></div></div><dl class="details-list"><dt>Category</dt><dd id="publicCategory"></dd><dt>Address</dt><dd id="publicAddress"></dd><dt>Services</dt><dd id="publicServices"></dd><dt>Payment methods</dt><dd id="publicPayments"></dd><dt>Menu explanations</dt><dd id="publicMenu"></dd><dt>Facilities</dt><dd id="publicFacilities"></dd></dl></article>
                <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Speak with confidence</span><h2>Approved phrases</h2></div></div><div id="publicPhrases" class="scenario-steps"></div><div class="divider"></div><h3>Ask another question</h3><label>Question<input id="publicCustomQuestion" maxlength="250" placeholder="Type a short tourism-service question"></label><button id="sendCustomQuestion" class="primary">Send question</button><div id="questionStatus" class="meta-box"></div><div id="visitorReplyCard" class="visitor-reply-card" hidden><span class="eyebrow">Your question</span><strong id="visitorReplyStatus">Waiting for the business</strong><p id="visitorReplyText">You can return to this page and check for a reply.</p><button id="checkVisitorReply" class="secondary" type="button">Check for reply</button></div></article>
            </div>
            <div class="grid-two mt-large"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Helpful answers</span><h2>Frequently asked questions</h2></div></div><div id="publicFaqs" class="record-list"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Understand the local context</span><h2>Local terminology</h2></div></div><div id="publicTerms" class="record-list"></div></article></div>
        </section>
        <footer>Powered by TourLingo · Clearer travel communication in Malaysia</footer>
    </main>
    <div id="messageOverlay" class="emergency-overlay" role="dialog" aria-modal="true" aria-label="Approved business phrase" aria-hidden="true"><button id="closeOverlay" type="button" aria-label="Close message">×</button><strong id="overlaySource"></strong><strong id="overlayTranslation"></strong><span id="overlayExtra"></span></div>
<script>
const slug=<?= json_encode(
    $slug,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>,$=s=>document.querySelector(s),esc=v=>{const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML};
const publicThemeKey='jomcommunicate_theme';
let overlayReturnFocus=null;
function setPublicTheme(dark){document.documentElement.classList.toggle('dark-mode',dark);const button=$('#publicContrastButton');button.textContent=dark?'☀':'☾';button.setAttribute('aria-pressed',String(dark));button.setAttribute('aria-label',dark?'Enable light mode':'Enable dark mode');button.title=dark?'Enable light mode':'Enable dark mode';}
let publicDarkMode=false;try{publicDarkMode=localStorage.getItem(publicThemeKey)==='dark';}catch(error){}setPublicTheme(publicDarkMode);
$('#publicContrastButton').onclick=()=>{publicDarkMode=!document.documentElement.classList.contains('dark-mode');const applyTheme=()=>{setPublicTheme(publicDarkMode);try{localStorage.setItem(publicThemeKey,publicDarkMode?'dark':'light');}catch(error){}};if(document.startViewTransition&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches)document.startViewTransition(applyTheme);else applyTheme();};
function showLoadError(message){$('#publicStatus').hidden=false;$('#publicBusiness').hidden=true;$('#publicStatus').innerHTML='<span class="action-icon coral">!</span><div><span class="eyebrow">TourLingo business</span><h1>Business page unavailable</h1><p>'+esc(message)+'</p></div>';}
async function load(){try{const r=await fetch('api/public_business.php?slug='+encodeURIComponent(slug));const d=await r.json();if(!r.ok){showLoadError(d.message||'This business page could not be loaded.');return;}$('#publicStatus').hidden=true;$('#publicBusiness').hidden=false;render(d);}catch(error){showLoadError('We could not load this business page. Check your connection and try again.');}}
function render(d) {
    const lang = $('#publicLanguage').value, b = d.business;
    const localized = d.translations.find(x => x.language_code === lang);
    const profile = localized || d.translations.find(x => x.language_code === 'en') || b;
    for (const [id,key] of Object.entries({publicName:'name', publicCategory:'category', publicAddress:'address'})) $('#'+id).textContent = b[key] || 'Not provided';
    for (const [id,key] of Object.entries({publicDescription:'description', publicServices:'service_details', publicPayments:'payment_methods', publicMenu:'menu_details', publicFacilities:'facility_details'})) {
        $('#'+id).textContent = profile[key] ? ((!localized && lang !== 'en' ? 'English fallback: ' : '') + profile[key]) : 'Not provided in this language';
    }
    const items = d.phrases.filter(x => x.target_language === lang), faqs = d.faqs.filter(x => x.language_code === lang), terms = d.terms.filter(x => x.language_code === lang);
    $('#publicPhrases').innerHTML = items.map(x => '<button class="scenario-step public-phrase" data-id="'+x.id+'"><strong>'+esc(x.source_text)+'</strong><span>'+esc(x.translated_text)+'</span></button>').join('') || '<div class="empty-state">No approved phrases in this language.</div>';
    $('#publicFaqs').innerHTML = faqs.map(x => '<details><summary>'+esc(x.question)+'</summary><p>'+esc(x.answer)+'</p></details>').join('') || '<div class="empty-state">No FAQs in this language.</div>';
    $('#publicTerms').innerHTML = terms.map(x => '<div class="record-item"><div><strong>'+esc(x.term)+'</strong><small>'+esc(x.explanation)+'</small></div></div>').join('') || '<div class="empty-state">No terms in this language.</div>';
    document.querySelectorAll('.public-phrase').forEach(button => button.onclick = () => {
        const row = items.find(p => String(p.id) === button.dataset.id);
        openOverlay(row.translated_text, row.suggested_reply || '', 'Approved business quick reply');
        recordQuestion(row.category, row.source_text).catch(() => {});
    });
    $('#publicLanguage').onchange = () => render(d);
}
async function recordQuestion(category, question, requiresReply = false) {
    const response = await fetch('api/public_business.php?slug='+encodeURIComponent(slug), {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({category, language:$('#publicLanguage').value, question, requires_reply:requiresReply})});
    if (!response.ok) throw new Error('The question could not be sent. Please try again.');
    return response.json();
}
function questionStorageKey(){return 'tourlingo_question:'+slug;}
// Keep separate receipts so a second question cannot hide the first answer.
let receipts = [];
try {
    receipts = JSON.parse(localStorage.getItem(questionStorageKey()+':receipts') || '[]');
    if (!Array.isArray(receipts)) receipts = [];
    const legacy = localStorage.getItem(questionStorageKey());
    if (legacy && !receipts.some(row => row.token === legacy)) receipts.push({token:legacy, question:'Earlier question'});
} catch (e) {}
async function checkQuestionReply() {
    if (!receipts.length) return;
    $('#visitorReplyCard').hidden = false;
    $('#visitorReplyStatus').textContent = 'Your questions';
    const results = await Promise.all(receipts.map(async row => {
        try {
            const r = await fetch('api/public_business.php?slug='+encodeURIComponent(slug)+'&question_token='+encodeURIComponent(row.token));
            if (!r.ok) throw new Error('Reply unavailable');
            const q = (await r.json()).question;
            return row.question + ': ' + (q.reply_text || q.status.replace('_',' ') + ' — no reply yet.');
        } catch (e) { return row.question + ': Could not check the reply. Please try again.'; }
    }));
    $('#visitorReplyText').replaceChildren(...results.map(text => { const p = document.createElement('span'); p.style.display='block'; p.textContent=text; return p; }));
}
$('#sendCustomQuestion').addEventListener('click', async () => {
    const question = $('#publicCustomQuestion').value.trim();
    if (!question) { $('#questionStatus').textContent='Enter a question before sending.'; $('#publicCustomQuestion').focus(); return; }
    const button = $('#sendCustomQuestion');
    button.disabled = true;
    try {
        const result = await recordQuestion('Custom question', question, true);
        if (!result.question_token) throw new Error('Missing question receipt. Please try again.');
        receipts.unshift({token:result.question_token, question});
        let saved = true;
        try { localStorage.setItem(questionStorageKey()+':receipts', JSON.stringify(receipts)); } catch (e) { saved = false; }
        $('#publicCustomQuestion').value='';
        $('#questionStatus').textContent = saved ? 'Question sent to the business.' : 'Question sent. Keep this page open: your browser could not save the reply receipt.';
        await checkQuestionReply();
    } catch (e) { $('#questionStatus').textContent='The question could not be sent. Check your connection and try again.'; }
    finally { button.disabled = false; }
});
$('#checkVisitorReply').onclick=checkQuestionReply;
checkQuestionReply();
function openOverlay(source,translation,extra=''){overlayReturnFocus=document.activeElement instanceof HTMLElement?document.activeElement:null;$('#overlaySource').textContent=source;$('#overlayTranslation').textContent=translation;$('#overlayExtra').textContent=extra;$('#messageOverlay').classList.add('open');$('#messageOverlay').setAttribute('aria-hidden','false');$('#closeOverlay').focus();}
function closeOverlay(){if(!$('#messageOverlay').classList.contains('open'))return;$('#messageOverlay').classList.remove('open');$('#messageOverlay').setAttribute('aria-hidden','true');overlayReturnFocus?.focus();overlayReturnFocus=null;}
$('#closeOverlay').onclick=closeOverlay;$('#messageOverlay').onclick=event=>{if(event.target===event.currentTarget)closeOverlay();};document.addEventListener('keydown',event=>{if(!$('#messageOverlay').classList.contains('open'))return;if(event.key==='Escape'){event.preventDefault();closeOverlay();}else if(event.key==='Tab'){event.preventDefault();$('#closeOverlay').focus();}});load();
</script>
</body>
</html>
