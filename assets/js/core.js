(function(root,factory){
    const api=factory();
    if(typeof module==='object'&&module.exports)module.exports=api;
    else root.JomCore=api;
}(typeof globalThis!=='undefined'?globalThis:this,function(){
    'use strict';
    function twoWayLanguages(source,target,detected){
        return {source:target,target:source==='auto'?detected:source};
    }
    function confidenceLabel(confidence){
        return confidence===null||confidence===undefined
            ? 'Translation confidence: not provided by Google'
            : 'Confidence: '+Math.round(Number(confidence)*100)+'%';
    }
    function normalizedAnalyticsLanguage(language){
        return !language||language==='auto'?null:language;
    }
    return {twoWayLanguages,confidenceLabel,normalizedAnalyticsLanguage};
}));
