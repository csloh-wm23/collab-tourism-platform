'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const app = fs.readFileSync(require.resolve('../assets/js/app.js'), 'utf8');
const css = fs.readFileSync(require.resolve('../assets/css/styles.css'), 'utf8');
const classes = new Set();
const overlay = {
    classList: {
        add: name => classes.add(name),
        toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)
    },
    setAttribute() {}
};
const elements = { '#messageOverlay': overlay };
const context = vm.createContext({
    $: selector => elements[selector] ||= { textContent: '', focus() {} },
    document: { activeElement: null },
    HTMLElement: class {},
    overlayReturnFocus: null
});
vm.runInContext(app.slice(app.indexOf('    function openOverlay('), app.indexOf('    function closeOverlay(')), context);
vm.runInContext(app.slice(app.indexOf('    function showEmergencyCard('), app.indexOf('    async function buildEmergencyCard(')), context);
vm.runInContext("showEmergencyCard({source: 'Help', translation: 'Bantuan'})", context);
assert(classes.has('is-emergency'));
assert(classes.has('open'));
assert.equal(elements['#overlaySource'].textContent, 'Help');
vm.runInContext("openOverlay('Hello', 'Hai')", context);
assert(!classes.has('is-emergency'), 'Ordinary messages must reset the emergency colour');
assert.match(css, /\.emergency-overlay\.is-emergency\s*\{\s*background:\s*#b91c1c;/);
assert.match(css, /\.emergency-overlay\s*\{[^}]*background:\s*#000;/);
console.log('Emergency overlay checks passed: red emergency mode, text retained, normal mode reset.');
