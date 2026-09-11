(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.JomCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function twoWayLanguages(source, target, detected) {
        return { source: target, target: source === 'auto' ? detected : source };
    }
    function confidenceLabel(confidence) {
        return confidence === null || confidence === undefined
            ? 'Translation confidence: not provided by Google'
            : 'Confidence: ' + Math.round(Number(confidence) * 100) + '%';
    }
    function normalizedAnalyticsLanguage(language) {
        return !language || language === 'auto' ? null : language;
    }
    function speechRecognitionLanguage(language, browserLanguage = 'en-US') {
        const locales = { en: 'en-US', ms: 'ms-MY', zh: 'zh-CN', id: 'id-ID', th: 'th-TH' };
        return locales[language] || browserLanguage || 'en-US';
    }
    function recoverableSpeechError(error) {
        return error === 'no-speech';
    }
    function speechBiasPhrases() {
        return [
            'cappuccino',
            'iced coffee',
            'coffee',
            'Kuala Lumpur',
            'Malaysia',
            'nasi lemak',
            'nasi campur',
            'mamak',
            'surau',
            'tapau',
            'roti canai',
            'teh tarik',
            'halal',
            'vegetarian',
            'train station',
            'bus station',
            'hotel',
            'restaurant',
            'emergency'
        ];
    }
    function normalizeSpeechTranscript(transcript) {
        const value = String(transcript || '')
            .replace(/\s+/g, ' ')
            .replace(/\s+([,.!?;:])/g, '$1')
            .trim();
        const exact = {
            capuccino: 'cappuccino',
            cappucino: 'cappuccino',
            tapuccino: 'cappuccino',
            'cup of chino': 'cappuccino',
            'couple chino': 'cappuccino'
        };
        const corrected = exact[value.toLowerCase()];
        return corrected || value.replace(/\b(?:capuccino|cappucino|tapuccino)\b/gi, 'cappuccino');
    }
    function bestSpeechAlternative(result, phrases = speechBiasPhrases()) {
        const alternatives = Array.from(result || []);
        if (!alternatives.length) return { transcript: '', confidence: 0 };
        const vocabulary = phrases.map((value) => String(value).toLowerCase());
        const score = (alternative) => {
            const transcript = normalizeSpeechTranscript(alternative.transcript).toLowerCase();
            const bias = vocabulary.reduce(
                (best, phrase) =>
                    Math.max(
                        best,
                        transcript === phrase ? 2 : transcript.includes(phrase) ? 0.35 : 0
                    ),
                0
            );
            return (Number(alternative.confidence) || 0) + bias;
        };
        const selected = alternatives.reduce((best, item) =>
            score(item) > score(best) ? item : best
        );
        return {
            transcript: normalizeSpeechTranscript(selected.transcript),
            confidence: Number(selected.confidence) || 0
        };
    }
    return {
        twoWayLanguages,
        confidenceLabel,
        normalizedAnalyticsLanguage,
        speechRecognitionLanguage,
        recoverableSpeechError,
        speechBiasPhrases,
        normalizeSpeechTranscript,
        bestSpeechAlternative
    };
});
