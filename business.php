<?php
declare(strict_types=1);
$slug = trim((string)($_GET['slug'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Traveller-friendly business information and approved multilingual phrases from TourLingo.">
    <title>Tourism business · TourLingo</title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= rawurlencode((string)(filemtime(__DIR__ . '/assets/css/styles.css') ?: '1')) ?>">
</head>
<body class="public-business">
    <header class="public-topbar">
        <a class="brand public-brand" href="index.php"><span class="brand-mark">T</span><span>TourLingo<small>Travel with confidence</small></span></a>
        <a class="secondary" href="index.php">Open travel tools</a>
    </header>
    <main class="content-wrap public-wrap">
        <div id="publicStatus" class="card-panel public-loading"><span class="action-icon aqua">⌂</span><div><span class="eyebrow">TourLingo business</span><h1>Loading business information…</h1><p>Please wait while we prepare the traveller page.</p></div></div>
        <section id="publicBusiness" hidden>
            <div class="public-hero">
                <div><span class="eyebrow light-eyebrow">Verified tourism business</span><h1 id="publicName"></h1><p id="publicDescription"></p></div>
                <label>Your language<select id="publicLanguage"><option value="en">English</option><option value="ms">Bahasa Malaysia</option><option value="zh">Mandarin Chinese</option><option value="id">Indonesian</option><option value="th">Thai</option></select></label>
            </div>
            <div class="grid-two public-primary-grid">
                <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Before you visit</span><h2>Service information</h2></div></div><dl class="details-list"><dt>Category</dt><dd id="publicCategory"></dd><dt>Address</dt><dd id="publicAddress"></dd><dt>Services</dt><dd id="publicServices"></dd><dt>Payment methods</dt><dd id="publicPayments"></dd><dt>Menu explanations</dt><dd id="publicMenu"></dd><dt>Facilities</dt><dd id="publicFacilities"></dd></dl></article>
                <article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Speak with confidence</span><h2>Approved phrases</h2></div></div><div id="publicPhrases" class="scenario-steps"></div><div class="divider"></div><h3>Ask another question</h3><label>Question<input id="publicCustomQuestion" maxlength="250" placeholder="Type a short tourism-service question"></label><button id="sendCustomQuestion" class="primary">Send question</button><div id="questionStatus" class="meta-box"></div></article>
            </div>
            <div class="grid-two mt-large"><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Helpful answers</span><h2>Frequently asked questions</h2></div></div><div id="publicFaqs" class="record-list"></div></article><article class="card-panel"><div class="card-title-row"><div><span class="eyebrow">Understand the local context</span><h2>Local terminology</h2></div></div><div id="publicTerms" class="record-list"></div></article></div>
        </section>
        <footer>Powered by TourLingo · Clearer travel communication in Malaysia</footer>
    </main>
    <div id="messageOverlay" class="emergency-overlay" aria-hidden="true"><button id="closeOverlay" aria-label="Close message">×</button><strong id="overlaySource"></strong><strong id="overlayTranslation"></strong><span id="overlayExtra"></span></div>
<script>
const slug=<?= json_encode($slug, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,$=s=>document.querySelector(s),esc=v=>{const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML};
async function load(){const r=await fetch('api/public_business.php?slug='+encodeURIComponent(slug));const d=await r.json();if(!r.ok){$('#publicStatus').innerHTML='<span class="action-icon coral">!</span><div><span class="eyebrow">TourLingo business</span><h1>Business page unavailable</h1><p>'+esc(d.message)+'</p></div>';return;}$('#publicStatus').hidden=true;$('#publicBusiness').hidden=false;render(d);}
function render(d){const lang=$('#publicLanguage').value,b=d.business,profile=d.translations.find(x=>x.language_code===lang)||d.translations.find(x=>x.language_code==='en')||b;for(const [id,key] of Object.entries({publicName:'name',publicCategory:'category',publicAddress:'address'}))$('#'+id).textContent=b[key]||'Not provided';for(const [id,key] of Object.entries({publicDescription:'description',publicServices:'service_details',publicPayments:'payment_methods',publicMenu:'menu_details',publicFacilities:'facility_details'}))$('#'+id).textContent=profile[key]||'Not provided in this language';const items=d.phrases.filter(x=>x.target_language===lang),faqs=d.faqs.filter(x=>x.language_code===lang),terms=d.terms.filter(x=>x.language_code===lang);$('#publicPhrases').innerHTML=items.map(x=>'<button class="scenario-step public-phrase" data-id="'+x.id+'" data-category="'+esc(x.category)+'" data-question="'+esc(x.source_text)+'"><strong>'+esc(x.source_text)+'</strong><span>'+esc(x.translated_text)+'</span></button>').join('')||'<div class="empty-state">No approved phrases in this language.</div>';$('#publicFaqs').innerHTML=faqs.map(x=>'<details><summary>'+esc(x.question)+'</summary><p>'+esc(x.answer)+'</p></details>').join('')||'<div class="empty-state">No FAQs in this language.</div>';$('#publicTerms').innerHTML=terms.map(x=>'<div class="record-item"><div><strong>'+esc(x.term)+'</strong><small>'+esc(x.explanation)+'</small></div></div>').join('')||'<div class="empty-state">No terms in this language.</div>';document.querySelectorAll('.public-phrase').forEach(x=>x.onclick=async()=>{const row=items.find(p=>String(p.id)===x.dataset.id);$('#overlaySource').textContent=row.translated_text;$('#overlayTranslation').textContent=row.suggested_reply||'';$('#overlayExtra').textContent='Approved business quick reply';$('#messageOverlay').classList.add('open');await recordQuestion(x.dataset.category,x.dataset.question);});$('#publicLanguage').onchange=()=>render(d);$('#sendCustomQuestion').onclick=async()=>{const question=$('#publicCustomQuestion').value.trim();if(!question)return;await recordQuestion('Custom question',question);$('#questionStatus').textContent='Question recorded for the business. Use the approved phrases above for an immediate reply.';$('#publicCustomQuestion').value='';};}
async function recordQuestion(category,question){await fetch('api/public_business.php?slug='+encodeURIComponent(slug),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({category,language:$('#publicLanguage').value,question})});}
$('#closeOverlay').onclick=()=>$('#messageOverlay').classList.remove('open');load();
</script>
</body>
</html>
