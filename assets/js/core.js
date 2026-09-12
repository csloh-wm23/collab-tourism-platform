(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.JomCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function restoreConversation(row) {
        return {
            source: row.source, translation: row.translation, from: row.from, to: row.to,
            scenario: row.scenario || 'culture', confidence: row.confidence ?? null,
            confidenceSource: row.confidenceSource || '', alternatives: [], suggestions: [],
            matchedTerms: []
        };
    }
    function savedSource(row) {
        try {
            const metadata = typeof row.metadata === 'string' ? JSON.parse(row.metadata) : row.metadata;
            return metadata?.source_text || row.title;
        } catch (e) { return row.title; }
    }
    // A detected language is only valid for the exact message that was translated.
    function spellingLanguage(text, selected, detection) {
        if (selected !== 'auto') return selected;
        return detection && detection.text === text.trim() ? detection.language : null;
    }
    // A deliberately limited typo list, not a general spellchecker. Only listed
    // whole words are suggested; slang, names and unknown words are left alone.
    function spellingSuggestion(text, language) {
        const dictionaries = {
            en: {
                cappocino: 'cappuccino', capuccino: 'cappuccino', cappucino: 'cappuccino',
                cappuccinoo: 'cappuccino', restarant: 'restaurant', restuarant: 'restaurant',
                restraunt: 'restaurant', coffe: 'coffee', cofee: 'coffee',
                vegatarian: 'vegetarian', vegeterian: 'vegetarian', alergic: 'allergic',
                allgergy: 'allergy', accomodation: 'accommodation', accomodate: 'accommodate',
                resevation: 'reservation', reservaton: 'reservation', tickect: 'ticket',
                lugage: 'luggage', passsport: 'passport', emmergency: 'emergency',
                emergancy: 'emergency', recieve: 'receive', adress: 'address',
                seperate: 'separate', tommorow: 'tomorrow', thankyou: 'thank you'
            },
            ms: {
                terimakasih: 'terima kasih', silaakn: 'silakan',
                makann: 'makan', minumm: 'minum', makanann: 'makanan',
                minumann: 'minuman', tempahann: 'tempahan', bayarr: 'bayar',
                pembayran: 'pembayaran', kecemasn: 'kecemasan', kecemasaan: 'kecemasan',
                tandass: 'tandas', stesyen: 'stesen', restoren: 'restoran',
                restouran: 'restoran', tolongg: 'tolong'
            }
        };
        const dictionary = dictionaries[language];
        if (!dictionary) return null;
        const original = String(text || '');
        const corrected = original.replace(/[\p{L}\p{M}\p{N}_'-]+/gu, (word) => {
            const replacement = dictionary[word.toLowerCase()];
            if (!replacement) return word;
            if (word === word.toUpperCase()) return replacement.toUpperCase();
            if (/^[A-Z]/.test(word)) return replacement[0].toUpperCase() + replacement.slice(1);
            return replacement;
        });
        return corrected !== original && corrected.length <= 500 ? corrected : null;
    }
    function twoWayLanguages(source, target, detected) {
        return { source: target, target: source === 'auto' ? detected : source };
    }
    function quickReplies(language) {
        return ({
            en: ['Yes, please.', 'No, thank you.', 'Could you repeat that?'],
            ms: ['Ya, sila.', 'Tidak, terima kasih.', 'Boleh ulang sekali lagi?'],
            zh: ['好的，谢谢。', '不用了，谢谢。', '可以再说一遍吗？'],
            id: ['Ya, silakan.', 'Tidak, terima kasih.', 'Bisa diulangi?'],
            th: ['ได้เลย', 'ไม่ ขอบคุณ', 'ช่วยพูดอีกครั้งได้ไหม']
        })[language] || [];
    }
    function confidenceLabel(confidence) {
        return confidence === null || confidence === undefined
            ? ''
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
        restoreConversation,
        savedSource,
        spellingLanguage,
        spellingSuggestion,
        twoWayLanguages,
        quickReplies,
        confidenceLabel,
        normalizedAnalyticsLanguage,
        speechRecognitionLanguage,
        recoverableSpeechError,
        speechBiasPhrases,
        normalizeSpeechTranscript,
        bestSpeechAlternative
    };
});
