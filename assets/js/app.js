(function(){
'use strict';

const $=s=>document.querySelector(s);
const $$=s=>Array.from(document.querySelectorAll(s));
const storageKey='jomcommunicate_records_v2';
const privacyKey='jomcommunicate_privacy_v2';
let records=[];
let currentTranslation='Stesen kereta api terdekat di mana?';
let translationState={source:'Where is the nearest train station?',from:'en',to:'ms'};

const dictionary={
'en-ms':{
'where is the nearest train station?':'Stesen kereta api terdekat di mana?',
'how much is this?':'Berapakah harga ini?',
'i need help':'Saya perlukan bantuan',
'does this contain peanuts?':'Adakah ini mengandungi kacang tanah?',
'thank you':'Terima kasih'
},
'ms-en':{
'stesen kereta api terdekat di mana?':'Where is the nearest train station?',
'berapakah harga ini?':'How much is this?',
'saya perlukan bantuan':'I need help',
'terima kasih':'Thank you'
},
'en-zh':{'thank you':'谢谢','where is the nearest train station?':'最近的火车站在哪里？'},
'en-ta':{'thank you':'நன்றி','where is the nearest train station?':'அருகிலுள்ள ரயில் நிலையம் எங்கே?'}
};

const recognitionLocales={en:'en-US',ms:'ms-MY',zh:'zh-CN',ta:'ta-MY'};
const scenarios={
restaurant:[['Ask for a table','Meja untuk dua orang, sila.'],['Mention an allergy','Saya alah kepada kacang tanah.'],['Ask for the bill','Boleh saya minta bil?']],
transport:[['Name your destination','Saya mahu pergi ke KL Sentral.'],['Ask the fare','Berapakah tambangnya?'],['Confirm the stop','Adakah ini perhentian saya?']],
hotel:[['Give your booking name','Tempahan atas nama saya.'],['Ask about check-in','Boleh saya daftar masuk sekarang?'],['Request Wi-Fi','Apakah kata laluan Wi-Fi?']],
shopping:[['Ask the price','Berapakah harga ini?'],['Ask for another size','Ada saiz lain?'],['Pay by card','Boleh bayar dengan kad?']]
};

function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}
function toast(msg){const e=$('#toast');if(!e)return;e.textContent=msg;e.classList.add('show');setTimeout(()=>e.classList.remove('show'),2500);}
function localRecords(){try{return JSON.parse(localStorage.getItem(storageKey)||'[]');}catch(e){return[];}}
function saveLocal(list){localStorage.setItem(storageKey,JSON.stringify(list.slice(0,100)));}
async function apiMessage(response,fallback){
try{const data=await response.json();return data.message||fallback;}catch(e){return fallback;}
}
function usesServerHistory(){return window.JOM.authenticated&&['tourist','business'].includes(window.JOM.role);}

function translationIsCurrent(){
return !!translationState&&translationState.source===$('#sourceText').value.trim()&&translationState.from===$('#sourceLanguage').value&&translationState.to===$('#targetLanguage').value;
}
function setTranslationActions(enabled){
['#speakResult','#copyResult','#savePhrase'].forEach(selector=>{const button=$(selector);if(button)button.disabled=!enabled;});
}
function invalidateTranslation(){
translationState=null;currentTranslation='';
$('#translationResult').textContent='Translation out of date. Press Translate to refresh it.';
setTranslationActions(false);
}

function showPage(id){
$$('.page').forEach(p=>p.classList.toggle('active',p.id===id));
$$('[data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page===id));
$('.sidebar')?.classList.remove('open');
location.hash=id;
}

$$('[data-page]').forEach(b=>b.addEventListener('click',()=>showPage(b.dataset.page)));
$('#menuButton')?.addEventListener('click',()=>$('.sidebar')?.classList.toggle('open'));
$('#contrastButton')?.addEventListener('click',()=>document.body.classList.toggle('high-contrast'));
$('#todayLabel').textContent=new Intl.DateTimeFormat('en-MY',{weekday:'long',day:'numeric',month:'long'}).format(new Date());

function translatedValue(){
const source=$('#sourceText').value.trim();
const key=$('#sourceLanguage').value+'-'+$('#targetLanguage').value;
if($('#sourceLanguage').value===$('#targetLanguage').value)return source;
return (dictionary[key]||{})[source.toLowerCase()]||null;
}

async function translate(){
const source=$('#sourceText').value.trim();
if(!source){toast('Enter a message first.');return;}
const result=translatedValue();
if(!result){
currentTranslation='';translationState=null;
$('#translationResult').textContent='Demo translation unavailable for this phrase.';
setTranslationActions(false);
toast('This demo dictionary does not contain that phrase.');
return;
}
currentTranslation=result;
translationState={source,from:$('#sourceLanguage').value,to:$('#targetLanguage').value};
$('#translationResult').textContent=result;
setTranslationActions(true);

const historyEnabled=$('#historyConsent')?$('#historyConsent').checked:usesServerHistory();
if(historyEnabled){
await saveRecord({record_type:'translation',title:source,content:result,metadata:{from:$('#sourceLanguage').value,to:$('#targetLanguage').value}});
}
}

async function azureSpeak(text,language){
if(!text||!translationIsCurrent()){toast('Press Translate again before using speech.');return;}
const button=$('#speakResult');
const old=button?.textContent;
if(button){button.disabled=true;button.textContent='Generating voice…';}
try{
const response=await fetch('api/speech.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text,language})});
if(!response.ok){
let message='Azure speech failed.';
try{const data=await response.json();message=data.message||message;}catch(e){}
throw new Error(message);
}
const blob=await response.blob();
const url=URL.createObjectURL(blob);
const audio=new Audio(url);
audio.addEventListener('ended',()=>URL.revokeObjectURL(url),{once:true});
await audio.play();
}catch(e){
toast(e.message||'Could not play speech.');
}finally{
if(button){button.disabled=false;button.textContent=old;}
}
}

function listen(language,callback){
const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
if(!SR){toast('Speech recognition is not supported in this browser.');return;}
const rec=new SR();
rec.lang=recognitionLocales[language]||'en-US';
rec.interimResults=false;
rec.onstart=()=>toast('Listening…');
rec.onerror=()=>toast('Could not hear you. Try again.');
rec.onresult=e=>callback(e.results[0][0].transcript);
rec.start();
}

async function loadRecords(){
if(usesServerHistory()){
try{
const r=await fetch('api/records.php',{headers:{Accept:'application/json'}});
if(!r.ok)throw new Error(await apiMessage(r,'Server history unavailable.'));
records=(await r.json()).records||[];
}catch(e){records=[];toast(e.message);}
}else if(!window.JOM.authenticated)records=localRecords();
else records=[];
renderRecords();
}

async function saveRecord(record){
record.created_at=new Date().toISOString();
if(usesServerHistory()){
try{
const r=await fetch('api/records.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...record,csrf:window.JOM.csrf})});
if(!r.ok)throw new Error(await apiMessage(r,'Server history unavailable.'));
record.id=(await r.json()).id;
records.unshift(record);renderRecords();return;
}catch(e){toast(e.message||'Server history unavailable; saved in this browser.');}
}
record.id='local-'+Date.now()+'-'+Math.random().toString(16).slice(2);
const local=localRecords();local.unshift(record);saveLocal(local);
records.unshift(record);renderRecords();
}

async function deleteRecord(id){
if(String(id).startsWith('local-')){
const local=localRecords().filter(r=>String(r.id)!==String(id));saveLocal(local);
}else if(usesServerHistory()){
const r=await fetch('api/records.php?id='+encodeURIComponent(id),{method:'DELETE',headers:{'X-CSRF-Token':window.JOM.csrf}});
if(!r.ok){toast(await apiMessage(r,'Could not delete record.'));return;}
}
records=records.filter(r=>String(r.id)!==String(id));renderRecords();
}

function recordHtml(r){
return '<div class="record-item"><div><strong>'+escapeHtml(r.title)+'</strong><small>'+escapeHtml(r.content)+'</small></div><button data-delete-record="'+escapeHtml(r.id)+'">×</button></div>';
}
function renderRecords(){
const translations=records.filter(r=>r.record_type==='translation').slice(0,5);
const phrases=records.filter(r=>r.record_type==='phrase').slice(0,20);
$('#recentTranslations').innerHTML=translations.length?translations.map(recordHtml).join(''):'<div class="empty-state">No translation history yet.</div>';
$('#savedPhrases').innerHTML=phrases.length?phrases.map(recordHtml).join(''):'<div class="empty-state">No saved phrases yet.</div>';
$$('[data-delete-record]').forEach(b=>b.onclick=()=>deleteRecord(b.dataset.deleteRecord));
}

function renderScenario(){
const steps=scenarios[$('#scenarioSelect').value]||[];
$('#scenarioSteps').innerHTML=steps.map((s,i)=>'<div class="scenario-step"><strong>'+(i+1)+'. '+escapeHtml(s[0])+'</strong><span>'+escapeHtml(s[1])+'</span></div>').join('');
}

function updateCharacterCount(){$('#characterCount').textContent=$('#sourceText').value.length;}
$('#sourceText')?.addEventListener('input',()=>{updateCharacterCount();invalidateTranslation();});
$('#sourceLanguage')?.addEventListener('change',invalidateTranslation);
$('#targetLanguage')?.addEventListener('change',invalidateTranslation);
$('#translateButton')?.addEventListener('click',translate);
$('#swapLanguages')?.addEventListener('click',()=>{
const a=$('#sourceLanguage').value,b=$('#targetLanguage').value;$('#sourceLanguage').value=b;$('#targetLanguage').value=a;
invalidateTranslation();
});
$('#listenInput')?.addEventListener('click',()=>listen($('#sourceLanguage').value,text=>{$('#sourceText').value=text;updateCharacterCount();invalidateTranslation();}));
$('#speakResult')?.addEventListener('click',()=>azureSpeak(currentTranslation,$('#targetLanguage').value));
$('#copyResult')?.addEventListener('click',async()=>{
if(!translationIsCurrent()){toast('Press Translate again before copying.');return;}
try{await navigator.clipboard.writeText(currentTranslation);toast('Copied.');}catch(e){toast('Copy is unavailable.');}
});
$('#savePhrase')?.addEventListener('click',async()=>{
const source=$('#sourceText').value.trim();
if(!source||!translationIsCurrent()){toast('Press Translate again before saving this phrase.');return;}
await saveRecord({record_type:'phrase',title:source,content:currentTranslation,metadata:{from:$('#sourceLanguage').value,to:$('#targetLanguage').value}});
toast('Phrase saved.');
});
$('#scenarioSelect')?.addEventListener('change',renderScenario);
$('#fullscreenEmergency')?.addEventListener('click',()=>{$('#emergencyOverlay').classList.add('open');$('#emergencyOverlay').setAttribute('aria-hidden','false');});
$('#closeEmergency')?.addEventListener('click',()=>{$('#emergencyOverlay').classList.remove('open');$('#emergencyOverlay').setAttribute('aria-hidden','true');});

function localPrivacy(){
try{return {...{save_history:true,analytics:false},...JSON.parse(localStorage.getItem(privacyKey)||'{}')};}
catch(e){return {save_history:true,analytics:false};}
}
function applyPrivacy(preferences){
if($('#historyConsent'))$('#historyConsent').checked=!!preferences.save_history;
if($('#analyticsConsent'))$('#analyticsConsent').checked=!!preferences.analytics;
}
async function loadPrivacyPreferences(){
if(window.JOM.authenticated&&window.JOM.role==='tourist'){
try{
const response=await fetch('api/preferences.php',{headers:{Accept:'application/json'}});
if(!response.ok)throw new Error(await apiMessage(response,'Could not load privacy choices.'));
applyPrivacy((await response.json()).preferences||{});return;
}catch(e){toast(e.message);}
}
applyPrivacy(localPrivacy());
}
async function savePrivacyPreferences(){
const preferences={save_history:$('#historyConsent').checked,analytics:$('#analyticsConsent').checked};
if(window.JOM.authenticated&&window.JOM.role==='tourist'){
const response=await fetch('api/preferences.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...preferences,csrf:window.JOM.csrf})});
if(!response.ok){toast(await apiMessage(response,'Could not save privacy choices.'));return;}
toast('Privacy choices saved.');return;
}
localStorage.setItem(privacyKey,JSON.stringify(preferences));
toast('Privacy choices saved in this browser.');
}
$('#historyConsent')?.addEventListener('change',savePrivacyPreferences);
$('#analyticsConsent')?.addEventListener('change',savePrivacyPreferences);

async function loadBusiness(){
if(!$('#businessName'))return;
try{
const r=await fetch('api/business.php');if(!r.ok)throw new Error(await apiMessage(r,'Business profile unavailable.'));
const data=await r.json(),b=data.business;
$('#businessName').value=b.name||'';$('#businessCategory').value=b.category||'';$('#businessAddress').value=b.address||'';
$('#businessPhrases').innerHTML=data.phrases.length?'<table class="data-table"><thead><tr><th>English</th><th>Translation</th><th>Language</th><th>Category</th></tr></thead><tbody>'+data.phrases.map(p=>'<tr><td>'+escapeHtml(p.source_text)+'</td><td>'+escapeHtml(p.translated_text)+'</td><td>'+escapeHtml(p.target_language)+'</td><td>'+escapeHtml(p.category)+'</td></tr>').join('')+'</tbody></table>':'<div class="empty-state">No phrases yet.</div>';
}catch(e){$('#businessPhrases').innerHTML='<div class="empty-state">'+escapeHtml(e.message||'Business profile unavailable.')+'</div>';}
}
$('#saveBusiness')?.addEventListener('click',async()=>{
const body={action:'save_profile',name:$('#businessName').value,category:$('#businessCategory').value,address:$('#businessAddress').value,csrf:window.JOM.csrf};
const r=await fetch('api/business.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
toast(r.ok?'Business profile saved.':await apiMessage(r,'Could not save business profile.'));if(r.ok)loadBusiness();
});
$('#addBusinessPhrase')?.addEventListener('click',async()=>{
const body={action:'add_phrase',source_text:$('#phraseSource').value,translated_text:$('#phraseTranslated').value,target_language:$('#phraseTarget').value,category:$('#phraseCategory').value,csrf:window.JOM.csrf};
const r=await fetch('api/business.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
if(r.ok){$('#phraseSource').value='';$('#phraseTranslated').value='';toast('Phrase added.');loadBusiness();}else toast(await apiMessage(r,'Could not add phrase.'));
});

async function loadInsights(){
if(!['editor','admin'].includes(window.JOM.role)||!$('#insightTranslations'))return;
try{
const response=await fetch('api/insights.php',{headers:{Accept:'application/json'}});
if(!response.ok)throw new Error(await apiMessage(response,'Insights unavailable.'));
const stats=(await response.json()).stats;
$('#insightTranslations').textContent=stats.translations;
$('#insightPhrases').textContent=stats.phrases;
$('#insightBusinesses').textContent=stats.businesses;
$('#insightPending').textContent=stats.pending_businesses;
}catch(e){toast(e.message);}
}

async function loadAdmin(){
if(window.JOM.role!=='admin'||!$('#adminUsers'))return;
try{
const r=await fetch('api/admin.php');if(!r.ok)throw new Error(await apiMessage(r,'Admin data unavailable.'));
const d=await r.json();
$('#adminUsers').textContent=d.stats.users;$('#adminBusinesses').textContent=d.stats.businesses;$('#adminPending').textContent=d.stats.pending;$('#adminTranslations').textContent=d.stats.translations;
$('#pendingBusinesses').innerHTML=d.pending.length?d.pending.map(b=>'<div class="record-item"><div><strong>'+escapeHtml(b.name)+'</strong><small>'+escapeHtml(b.category)+' · '+escapeHtml(b.email)+'</small></div><span><button class="approve" data-bid="'+b.id+'" data-decision="approve">Approve</button> <button class="reject" data-bid="'+b.id+'" data-decision="reject">Reject</button></span></div>').join(''):'<div class="empty-state">No pending businesses.</div>';
$$('[data-decision]').forEach(btn=>btn.onclick=()=>decideBusiness(btn.dataset.bid,btn.dataset.decision));
}catch(e){toast(e.message||'Admin data unavailable.');}
}
async function decideBusiness(id,decision){
const r=await fetch('api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({business_id:Number(id),decision,csrf:window.JOM.csrf})});
toast(r.ok?'Registration updated.':await apiMessage(r,'Could not update registration.'));if(r.ok){loadAdmin();loadInsights();}
}

async function initialize(){
updateCharacterCount();setTranslationActions(true);renderScenario();
await loadPrivacyPreferences();
await Promise.all([loadRecords(),loadBusiness(),loadInsights(),loadAdmin()]);
}
initialize();
const start=location.hash.slice(1);if(start&&document.getElementById(start)?.classList.contains('page'))showPage(start);
}());
