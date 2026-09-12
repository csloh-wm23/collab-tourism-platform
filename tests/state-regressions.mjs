// Execute the real frontend handlers with deterministic delayed/failing transports.
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import core from '../assets/js/core.js';
const app = readFileSync(new URL('../assets/js/app.js', import.meta.url),'utf8');
const extract = (start,end) => app.slice(app.indexOf(start),app.indexOf(end,app.indexOf(start)));
let checks = 0;
function check(name,value,expected) { assert.deepEqual(value,expected,name); checks++; console.log('PASS '+name); }
function context() {
    const nodes = new Map();
    const $ = key => { if (!nodes.has(key)) nodes.set(key,{value:'',textContent:'',innerHTML:'',checked:false,disabled:false}); return nodes.get(key); };
    $('#sourceText').value='I want tea'; $('#sourceLanguage').value='en'; $('#targetLanguage').value='ms'; $('#historyConsent').checked=true;
    const notices=[], saved=[], swaps=[], tracked=[], conversations=[], spoken=[];
    const ctx = vm.createContext({$,window:{JOM:{authenticated:true},JomCore:core},dictionary:{},translationScenario:'culture',translationVersion:0,current:null,spellingDetection:null,names:{en:'English',ms:'BM'},toast:m=>notices.push(m),actions:()=>{},renderTwoWay:()=>{},updateSpellingSuggestion:()=>{},escapeHtml:String,message:async(r,f)=>f,addConversation:r=>conversations.push(r),saveRecord:async r=>saved.push(r),track:async(...a)=>tracked.push(a),prepareNextTwoWayTurn:r=>swaps.push(r),speak:async()=>spoken.push(1)});
    ctx.activeTranslationRequest = 0;
    vm.runInContext(extract('    async function translate() {','    function renderTwoWay() {'),ctx);
    return {ctx,$,saved,swaps,tracked,conversations,notices};
}
{
    const c=context(); let finish;
    c.ctx.jsonFetch=()=>new Promise(resolve=>{finish=resolve;});
    const running=c.ctx.translate();
    c.$('#sourceText').value='I want water'; c.ctx.translationVersion++;
    finish({ok:true,json:async()=>({translation:'Saya mahu teh',detected_language:'en'})}); await running;
    check('Delayed result cannot overwrite changed input',c.$('#translationResult').textContent,'');
    check('Stale result not saved',c.saved.length,0);
    check('Stale result does not reverse direction',c.swaps.length,0);
}
{
    const c=context(); let saveFinished;
    c.$('#twoWayMode').checked=true;
    c.ctx.jsonFetch=async()=>({ok:true,json:async()=>({translation:'Saya mahu teh',detected_language:'en'})});
    c.ctx.saveRecord=()=>new Promise(resolve=>{saveFinished=resolve;});
    const running=c.ctx.translate();
    while (!saveFinished) await Promise.resolve();
    c.ctx.translationVersion++; c.ctx.current={source:'new message'};
    saveFinished(); await running;
    check('Edit during history save does not swap new input',c.swaps.length,0);
    check('Completed exchange uses immutable snapshot',c.conversations[0].source,'I want tea');
}
{
    const c=context(); c.ctx.jsonFetch=async()=>({ok:true,json:async()=>({translation:'Saya mahu teh',detected_language:'en'})});
    await c.ctx.translate();
    check('Ordinary translation renders',c.$('#translationResult').textContent,'Saya mahu teh');
    check('Two-way disabled creates no timeline',c.conversations.length,0);
}
{
    const stored=new Map(); let history=false;
    const ctx=vm.createContext({window:{JOM:{authenticated:true}},$:()=>({checked:history}),conversationKey:'user:1:conversation',conversationMessages:[{source:'private'}],localStorage:{setItem:(k,v)=>stored.set(k,v),removeItem:k=>stored.delete(k)}});
    vm.runInContext(extract('    function persistConversation() {','    function addConversation('),ctx);
    ctx.persistConversation(); check('No persisted timeline without consent',stored.size,0);
    history=true; ctx.persistConversation(); check('Consented timeline saved',stored.size,1);
    history=false; ctx.persistConversation(); check('Withdrawing history consent removes persisted timeline',stored.size,0);
}
{
    let posts=0; const notices=[];
    const ctx=vm.createContext({current:{source:'test',translation:'ujian',from:'en',to:'ms',scenario:'culture',confidence:null,matchedTerms:[]},window:{JOM:{csrf:'test'}},prompt:()=>null,confirm:()=>true,jsonFetch:async()=>{posts++;throw Error('offline');},toast:m=>notices.push(m),message:async()=>''});
    vm.runInContext(extract('    async function report() {',"    $('#sourceText')?.addEventListener"),ctx);
    await ctx.report(); check('Cancelled report sends nothing',posts,0);
    ctx.prompt=()=>''; await ctx.report(); check('Offline report shows failure',notices.some(m=>m.includes('Could not submit')),true);
}
check('Account identity included in private key',app.includes("'tourlingo:user:' + (window.JOM.userId || 'guest')"),true);
{
    const record={id:4,is_favorite:0};
    let renders=0;
    const ctx=vm.createContext({window:{JOM:{authenticated:true,csrf:'test'}},serverHistory:()=>true,records:[record],jsonFetch:async()=>({ok:false}),fetch:async()=>({ok:false}),message:async()=> 'Server refused',renderRecords:()=>renders++});
    vm.runInContext(extract('    async function saveRecord(row) {','    function recordRow('),ctx);
    await assert.rejects(ctx.saveRecord({title:'x'}));
    await assert.rejects(ctx.favorite(4,true));
    await assert.rejects(ctx.removeRecord(4));
    check('Failed saves do not add records',ctx.records.length,1);
    check('Failed favourite does not change state',record.is_favorite,0);
    check('Failed deletion does not remove the row',ctx.records[0].id,4);
    check('Failed mutations do not render false success',renders,0);
}
{
    const stored = new Map([['tourlingo:user:1:conversation','private'],['tourlingo:user:1:emergency','private'],['tourlingo:user:1:pack:Malaysia:hotel:ms','pack'],['tourlingo:user:2:conversation','other account'],['jomcommunicate_theme','dark']]);
    let listener;
    const ctx = vm.createContext({$:()=>({value:'x',dataset:{},addEventListener:(_,fn)=>{listener=fn;}}),confirm:()=>true,window:{JOM:{role:'tourist',csrf:'test'}},jsonFetch:async()=>({ok:true}),message:async()=>'',accountPrefix:'tourlingo:user:1:',records:[{}],conversationMessages:[{}],scenarioPhrases:[{}],localStorage:{get length(){return stored.size;},key:i=>[...stored.keys()][i],removeItem:k=>stored.delete(k)},invalidate(){},resetAssistantGuide(){},renderRecords(){},renderConversation(){},loadPreferences(){},loadProfile(){},toast(){}});
    vm.runInContext(extract("    $('#deleteJourneyData')?.addEventListener",'    function renderProfileImage'),ctx);
    await listener();
    check('Deletion clears this account caches only',[...stored.keys()],['tourlingo:user:2:conversation','jomcommunicate_theme']);
    check('Deletion clears in-memory conversation',ctx.conversationMessages.length,0);
}
{
    const source = readFileSync(new URL('../business.php',import.meta.url),'utf8');
    const start = source.indexOf("$('#sendCustomQuestion').addEventListener"), end = source.indexOf("$('#checkVisitorReply')",start);
    const nodes = new Map(); let handler;
    const $ = id => { if (!nodes.has(id)) nodes.set(id,{value:'Test visitor question',textContent:'',disabled:false,addEventListener:(_,fn)=>{handler=fn;}}); return nodes.get(id); };
    const ctx=vm.createContext({$,recordQuestion:async()=>{throw Error('offline');},receipts:[],questionStorageKey:()=> 'receipt',localStorage:{setItem(){}},checkQuestionReply:async()=>{}});
    vm.runInContext(source.slice(start,end),ctx);
    await handler();
    check('Disconnected visitor submission gives feedback',$('#questionStatus').textContent.includes('could not be sent'),true);
    check('Failed visitor question remains editable',$('#publicCustomQuestion').value,'Test visitor question');
    check('Visitor can retry after disconnection',$('#sendCustomQuestion').disabled,false);
}
console.log(`PASS: ${checks} total state and failure regressions`);
