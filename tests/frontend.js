'use strict';
const assert=require('node:assert/strict');
const core=require('../assets/js/core.js');

assert.deepEqual(core.twoWayLanguages('auto','ms','en'),{source:'ms',target:'en'});
assert.deepEqual(core.twoWayLanguages('en','th','en'),{source:'th',target:'en'});
assert.equal(core.confidenceLabel(null),'Translation confidence: not provided by Google');
assert.equal(core.confidenceLabel(0.74),'Confidence: 74%');
assert.equal(core.normalizedAnalyticsLanguage('auto'),null);
assert.equal(core.normalizedAnalyticsLanguage('id'),'id');
console.log('PASS: frontend conversation and confidence behaviour');
