'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const app = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/app.js'), 'utf8');
const start = app.indexOf('async function loadGlossary()');
const end = app.indexOf('function assistanceCacheKey', start);
assert(start > 0 && end > start);
async function run(fetch, authenticated = true) {
    const list = {innerHTML: 'unchanged'};
    const context = {window:{JOM:{authenticated}}, fetch, $:()=>list, $$:()=>[], escapeHtml:String};
    vm.createContext(context);
    await vm.runInContext(app.slice(start, end)+'; loadGlossary()', context);
    return list.innerHTML;
}
(async () => {
    assert.match(await run(async()=>({ok:false})), /could not be loaded/);
    assert.match(await run(async()=>{throw new Error('offline');}), /role="status"/);
    assert.match(await run(async()=>({ok:true,json:async()=>({terms:null})})), /could not be loaded/);
    assert.match(await run(async()=>({ok:true,json:async()=>({terms:[]})})), /No terminology/);
    assert.match(await run(async()=>({ok:true,json:async()=>({terms:[{term:'Tapau',explanation:'Take away',category:'Food'}]})})), /Tapau/);
    assert.equal(await run(async()=>{throw new Error('Must not fetch');}, false), 'unchanged');
    console.log('PASS: 6 glossary loading checks');
})().catch(e=>{console.error(e);process.exitCode=1;});
