<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Translation.php';

use TourLingo\Translation\{TranslationRequest, TranslationResult, TranslationProvider, TranslationContext, TranslationService, GoogleTranslationProvider, TranslationFailure};

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}

$valid = ['text' => 'Hello', 'from' => 'en', 'to' => 'ms'];
foreach (['text', 'from', 'to', 'scenario'] as $field) {
    foreach ([[], new stdClass(), true, 42] as $invalid) {
        try {
            TranslationRequest::fromArray(array_replace($valid, [$field => $invalid]));
            throw new RuntimeException('Accepted non-string ' . $field);
        } catch (InvalidArgumentException $e) { check(true, 'Rejected type'); }
    }
}
foreach ([['text'=>''], ['text'=>str_repeat('中',501)], ['from'=>'xx'], ['to'=>'auto'], ['scenario'=>'unknown']] as $invalid) {
    try {
        TranslationRequest::fromArray(array_replace($valid, $invalid));
        throw new RuntimeException('Accepted invalid request');
    } catch (InvalidArgumentException $e) { check(true, 'Rejected value'); }
}
$request = TranslationRequest::fromArray($valid);
check($request->scenario === 'culture', 'Default scenario');
check(TranslationRequest::fromArray(array_replace($valid,['text'=>str_repeat('中',500)]))->text === str_repeat('中',500), 'Unicode limit');
try { $request->text = 'changed'; throw new RuntimeException('Mutable request'); }
catch (Error $e) { check(true, 'Immutable request'); }

$provider = new class implements TranslationProvider {
    public int $calls = 0;
    public bool $fail = false;
    public function translate(TranslationRequest $request): TranslationResult {
        $this->calls++;
        if ($this->fail) { throw new TranslationFailure('Provider unavailable'); }
        return new TranslationResult('Helo', 'en', $request->scenario);
    }
};
$context = new class implements TranslationContext {
    public int $calls = 0;
    public bool $fail = false;
    public function lookup(TranslationRequest $request, string $translation): array {
        $this->calls++;
        if ($this->fail) { throw new RuntimeException('Database unavailable'); }
        return ['alternatives'=>['Hai'],'suggestions'=>[],'matched_terms'=>[]];
    }
};
$service = new TranslationService($provider, $context);
$same = $service->translate(TranslationRequest::fromArray(array_replace($valid,['to'=>'en'])))->jsonSerialize();
check($provider->calls === 0 && $context->calls === 0, 'Same language bypasses dependencies');
check($same['translation'] === 'Hello' && $same['confidence'] === 1, 'Same language result');
$result = $service->translate(TranslationRequest::fromArray(array_replace($valid,['from'=>'auto'])))->jsonSerialize();
check($result['detected_language'] === 'en', 'Detected source preserved');
check($result['translation'] === 'Helo' && $result['alternatives'] === ['Hai'], 'Enrichment');
check($result['confidence'] === null && $result['confidence_source'] === 'not_provided_by_google', 'No invented confidence');
check(json_decode(json_encode($service->translate($request)),true)['ok'] === true, 'JSON contract');
$context->fail = true;
$fallback = $service->translate($request)->jsonSerialize();
check($fallback['translation'] === 'Helo' && $fallback['alternatives'] === [], 'Context failure preserves translation');
$provider->fail = true;
$before = $context->calls;
try { $service->translate($request); throw new RuntimeException('Provider failure swallowed'); }
catch (TranslationFailure $e) { check($e->status === 502 && $context->calls === $before, 'Provider failure propagated'); }
try { (new GoogleTranslationProvider(''))->translate($request); throw new RuntimeException('Missing key accepted'); }
catch (TranslationFailure $e) { check($e->status === 503, 'Missing configuration'); }
echo "PASS: {$checks} translation OOAD regression checks (no network or persistent writes)\n";
