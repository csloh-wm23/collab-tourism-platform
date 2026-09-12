// Explicitly targets the disposable audit site. Never run mutations on the live DB.
import assert from 'node:assert/strict';
const base = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8097/';
if (base !== 'http://127.0.0.1:8097/') throw new Error('Use the isolated audit site on port 8097.');
class Client {
    cookies = new Map(); csrf = '';
    async req(path, method = 'GET', data, form = false) {
        const headers = {Cookie:[...this.cookies].map(([k,v]) => `${k}=${v}`).join('; ')};
        let body;
        if (data) { headers['Content-Type'] = form ? 'application/x-www-form-urlencoded' : 'application/json'; body = form ? new URLSearchParams({...data,csrf:this.csrf}) : JSON.stringify({csrf:this.csrf,...data}); }
        if (method === 'DELETE') headers['X-CSRF-Token'] = this.csrf;
        const r = await fetch(base+path,{method,headers,body,redirect:'manual'});
        for (const cookie of r.headers.getSetCookie()) { const [key,...value] = cookie.split(';')[0].split('='); this.cookies.set(key,value.join('=')); }
        const text = await r.text();
        this.csrf = text.match(/name="csrf" value="([^"]+)"/)?.[1] || text.match(/window.JOM=\{csrf:"([^"]+)"/)?.[1] || this.csrf;
        let json; try { json = JSON.parse(text); } catch {}
        return {status:r.status,json,text};
    }
    async login(email, password = 'TourLingoDemo#2026') {
        await this.req('login.php');
        assert.equal((await this.req('login.php','POST',{email,password},true)).status,302);
        return this.req('index.php');
    }
}
let checks = 0;
function check(name, actual, expected) { assert.deepEqual(actual, expected, name); checks++; console.log('PASS '+name); }
const guest = new Client();
await guest.req('index.php');
for (const api of ['assistance','glossary','report','report_review']) check('Guest denied '+api,(await guest.req('api/'+api+'.php')).status,403);
check('Guest translation available',(await guest.req('api/translate.php','POST',{text:'Hello',from:'en',to:'en'})).status,200);
const tourist = new Client(), business = new Client(), editor = new Client(), admin = new Client();
for (const [c,email] of [[tourist,'tourist.demo'],[business,'business.approved'],[editor,'editor.demo'],[admin,'admin.demo']]) {
    const page = await c.login(email+'@tourlingo.test');
    check(email+' account cache identity',/userId:\d+/.test(page.text),true);
}
for (const c of [tourist,business]) check('Non-staff report review denied',(await c.req('api/report_review.php')).status,403);
check('Missing seeded profile handled',(await tourist.req('api/tourist.php')).json?.ok,true);
const originalPreferences = (await tourist.req('api/preferences.php')).json.preferences;
try {
    await tourist.req('api/preferences.php','POST',{save_history:true,analytics:false});
    const text = '全'.repeat(500);
    const saved = await tourist.req('api/records.php','POST',{record_type:'translation',title:text,content:'Test output',source_language:'zh',target_language:'en'});
    check('Save full source',saved.status,201);
    const record = (await tourist.req('api/records.php')).json.records.find(row => row.id === saved.json.id);
    check('Original survives database round trip',JSON.parse(record.metadata).source_text,text);
    await tourist.req('api/records.php?id='+saved.json.id,'DELETE');
    await tourist.req('api/preferences.php','POST',{save_history:false,analytics:false});
    check('History consent enforced server-side',(await tourist.req('api/records.php','POST',{record_type:'translation',title:'No history',content:'Test'})).status,403);
} finally { await tourist.req('api/preferences.php','POST',originalPreferences); }
const marker = 'Regression report '+Date.now();
for (const shared of [false,true]) {
    const submitted = await tourist.req('api/report.php','POST',{source_text:marker,translated_text:'Test translation',source_language:'en',target_language:'ms',scenario:'culture',notes:marker+' '+shared,share_text:shared});
    check('Submit report '+shared,submitted.status,200);
    const report = (await editor.req('api/report_review.php')).json.reports.find(row => row.notes === marker+' '+shared);
    check('Sharing consent '+shared,report.source_text,shared ? marker : null);
    check('Editor review invalid CSRF',(await editor.req('api/report_review.php','POST',{id:report.id,csrf:'invalid'})).status,403);
    check('Editor marks reviewing',(await editor.req('api/report_review.php','POST',{id:report.id,previous_status:'open',status:'reviewing',note:'Disposable regression review'})).status,200);
    check('Concurrent stale decision rejected',(await admin.req('api/report_review.php','POST',{id:report.id,previous_status:'open',status:'resolved',note:'Stale'})).status,409);
    check('Admin resolves report',(await admin.req('api/report_review.php','POST',{id:report.id,previous_status:'reviewing',status:'resolved',note:'Disposable test completed; no provider changes'})).status,200);
    const reviewed = (await editor.req('api/report_review.php')).json.reports.find(row => row.id === report.id);
    check('Review history retained',reviewed.reviews.length,2);
}
check('Reject without feedback denied',(await admin.req('api/admin.php','POST',{business_id:1,decision:'reject'})).status,422);
const applicant = new Client(), suffix = Date.now();
await applicant.req('register.php');
check('Register isolated business',(await applicant.req('register.php','POST',{full_name:'Regression applicant',email:`fix-${suffix}@tourlingo.test`,role:'business',business_name:`Regression cafe ${suffix}`,category:'Food & drink',address:'Fictional regression address',password:'AuditFixture#2026',confirm_password:'AuditFixture#2026'},true)).status,302);
await applicant.login(`fix-${suffix}@tourlingo.test`,'AuditFixture#2026');
const application = (await applicant.req('api/business.php')).json.business;
check('Admin rejection with feedback',(await admin.req('api/admin.php','POST',{business_id:application.id,decision:'reject',reason:'Please supply the full fictional address.'})).status,200);
check('Applicant sees reason',(await applicant.req('api/business.php')).json.business.review_reason,'Please supply the full fictional address.');
check('Applicant resubmits corrected details',(await applicant.req('api/business.php','POST',{action:'resubmit_application',name:application.name,category:application.category,address:'Corrected fictional regression address'})).status,200);
check('Admin approves corrected application',(await admin.req('api/admin.php','POST',{business_id:application.id,decision:'approve'})).status,200);
check('Approved applicant can use studio',(await applicant.req('api/business.php')).json.approved,true);
// Reply to fictional browser-created questions only, when present.
const inbox = (await business.req('api/business.php')).json.inbox;
for (const item of inbox.filter(row => row.question_label.startsWith('FIX browser '))) {
    check('Answer browser test question',(await business.req('api/business.php','POST',{action:'update_inquiry',id:item.id,status:'answered',reply_text:'Reply for '+item.question_label})).status,200);
}
const insights = await editor.req('api/insights.php?location=NO-SUCH-PLACE');
check('Filtered activity empty',insights.json.events.length,0);
check('Unsupported filter scope explained',insights.json.filter_scope.includes('not retained'),true);
check('CSV describes same scope',(await editor.req('api/insights.php?format=csv&location=NO-SUCH-PLACE')).text.includes(insights.json.filter_scope),true);
console.log(`PASS: ${checks} isolated audit regressions`);
