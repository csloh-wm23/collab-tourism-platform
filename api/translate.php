<?php
declare(strict_types=1);

// Translation module: validate a message, ask Google to translate it, then add
// relevant local phrases before returning JSON to the browser.
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation.php';

// Use one response helper so successful results and errors share the same format.
function translation_reply(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Accept JSON sent by the translation form; reject other request methods.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    translation_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
}
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    translation_reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
}
// Validate the length and supported language/scenario codes before contacting Google.
try {
    $text = clean_text($input['text'] ?? '', 500, true);
    $from = clean_language($input['from'] ?? '', true);
    $to = clean_language($input['to'] ?? '');
    $scenario = clean_scenario($input['scenario'] ?? 'culture');
} catch (InvalidArgumentException $e) {
    translation_reply(['ok' => false, 'message' => $e->getMessage()], 422);
}

// No external translation is needed when both selected languages are identical.
if ($from !== 'auto' && $from === $to) {
    translation_reply([
        'ok' => true,
        'translation' => $text,
        'detected_language' => $from,
        'confidence' => 1,
        'confidence_source' => 'exact_same_language',
        'alternatives' => [],
        'suggestions' => [],
        'matched_terms' => [],
        'scenario' => $scenario,
    ]);
}

// Convert our language codes to Google's codes and check server configuration.
// The API key stays on the server rather than being exposed to the browser.
$codes = ['en' => 'en', 'ms' => 'ms', 'zh' => 'zh-CN', 'id' => 'id', 'th' => 'th'];
$key = trim((string) (getenv('GOOGLE_TRANSLATE_API_KEY') ?: ''));
if ($key === '') {
    translation_reply(
        ['ok' => false, 'message' => 'Google Cloud Translation is not configured on the server.'],
        503,
    );
}
if (!extension_loaded('curl')) {
    translation_reply(['ok' => false, 'message' => 'PHP cURL extension is required.'], 500);
}

// Omitting the source language lets Google detect it automatically.
$request = ['q' => $text, 'target' => $codes[$to], 'format' => 'text'];
if ($from !== 'auto') {
    $request['source'] = $codes[$from];
}
// Send the request with time limits so an unavailable provider cannot hang forever.
$ch = curl_init(
    'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key),
);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=UTF-8',
        'Accept: application/json',
    ],
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Handle connection errors and unsuccessful HTTP responses before reading the result.
if ($response === false || $error !== '') {
    translation_reply(
        ['ok' => false, 'message' => 'Unable to contact Google Cloud Translation.'],
        502,
    );
}
if ($status < 200 || $status >= 300) {
    error_log('Google Cloud Translation HTTP ' . $status);
    translation_reply(
        [
            'ok' => false,
            'message' => in_array($status, [400, 401, 403], true)
                ? 'Google Cloud Translation credentials or configuration were rejected.'
                : 'Translation is temporarily unavailable.',
        ],
        502,
    );
}

// Extract the translated text and map the detected language back to our own codes.
$item = json_decode((string) $response, true)['data']['translations'][0] ?? [];
$translation = $item['translatedText'] ?? null;
if (!is_string($translation) || $translation === '') {
    translation_reply(['ok' => false, 'message' => 'Translation is temporarily unavailable.'], 502);
}
$reverse = array_flip($codes);
$detected =
    $from === 'auto'
        ? $reverse[(string) ($item['detectedSourceLanguage'] ?? '')] ??
            (string) ($item['detectedSourceLanguage'] ?? 'unknown')
        : $from;

// Enrich the translation with local terms and curated phrases from the database.
// This optional lookup can fail without discarding the provider's translation.
$alternatives = [];
$suggestions = [];
$matchedTerms = [];
try {
    $db = database();
    $terms = $db->query('SELECT term,explanation FROM malaysian_terms ORDER BY term')->fetchAll();
    foreach ($terms as $term) {
        if (mb_stripos($text, (string) $term['term']) !== false) {
            $matchedTerms[] = (string) $term['term'];
            $alternatives[] = (string) $term['term'] . ': ' . (string) $term['explanation'];
        }
    }
    $phrase = $db->prepare(
        'SELECT translated_text FROM phrase_packs WHERE scenario=? AND language_code=? AND LOWER(source_text)=LOWER(?) ORDER BY id LIMIT 3',
    );
    $phrase->execute([$scenario, $to, $text]);
    foreach ($phrase->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
        if ($candidate !== $translation && !in_array($candidate, $alternatives, true)) {
            $alternatives[] = (string) $candidate;
        }
    }
    $context = $db->prepare(
        'SELECT source_text,translated_text,suggested_reply FROM phrase_packs WHERE scenario=? AND language_code=? ORDER BY id LIMIT 4',
    );
    $context->execute([$scenario, $to]);
    $suggestions = $context->fetchAll();
} catch (Throwable $e) {
    error_log('Translation context lookup: ' . $e->getMessage());
}
// Cloud Translation Basic v2 does not return a translation-confidence score.
// A null value is deliberately returned instead of displaying a fabricated percentage.
translation_reply([
    'ok' => true,
    'translation' => html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    'detected_language' => $detected,
    'confidence' => null,
    'confidence_source' => 'not_provided_by_google',
    'alternatives' => $alternatives,
    'suggestions' => $suggestions,
    'matched_terms' => $matchedTerms,
    'scenario' => $scenario,
]);
