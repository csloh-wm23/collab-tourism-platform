<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/auth.php';

function fail_translation(string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail_translation('Method not allowed.', 405);
}

// Limit anonymous and signed-in sessions to control accidental API usage/cost.
$now = time();
$recent = array_values(array_filter(
    is_array($_SESSION['translation_requests'] ?? null) ? $_SESSION['translation_requests'] : [],
    static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - 60
));
if (count($recent) >= 30) {
    header('Retry-After: 60');
    fail_translation('Too many translation requests. Please wait a minute and try again.', 429);
}
$recent[] = $now;
$_SESSION['translation_requests'] = $recent;

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    fail_translation('Invalid JSON body.', 400);
}

$text = trim((string)($input['text'] ?? ''));
$from = (string)($input['from'] ?? '');
$to = (string)($input['to'] ?? '');
$languages = ['en' => 'en', 'ms' => 'ms', 'zh' => 'zh-CN', 'ta' => 'ta'];

if ($text === '' || mb_strlen($text) > 500) {
    fail_translation('Translation text must contain 1 to 500 characters.', 422);
}
if (!isset($languages[$from], $languages[$to])) {
    fail_translation('Unsupported translation language.', 422);
}
if ($from === $to) {
    echo json_encode(['ok' => true, 'translation' => $text], JSON_UNESCAPED_UNICODE);
    exit;
}

$key = trim((string)(getenv('GOOGLE_TRANSLATE_API_KEY') ?: ''));
if ($key === '') {
    fail_translation('Google Cloud Translation is not configured on the server.', 503);
}
if (!extension_loaded('curl')) {
    fail_translation('PHP cURL extension is required.', 500);
}

$url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key);
$body = json_encode([
    'q' => $text,
    'source' => $languages[$from],
    'target' => $languages[$to],
    'format' => 'text',
], JSON_UNESCAPED_UNICODE);
$headers = [
    'Content-Type: application/json; charset=UTF-8',
    'Accept: application/json',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => $headers,
]);
$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $error !== '') {
    fail_translation('Unable to contact Google Cloud Translation.', 502);
}
if ($status < 200 || $status >= 300) {
    error_log('Google Cloud Translation returned HTTP ' . $status);
    fail_translation(
        in_array($status, [400, 401, 403], true)
            ? 'Google Cloud Translation credentials or configuration were rejected.'
            : 'Translation is temporarily unavailable.',
        502
    );
}

$data = json_decode((string)$response, true);
$translation = $data['data']['translations'][0]['translatedText'] ?? null;
if (!is_string($translation) || $translation === '') {
    error_log('Google Cloud Translation returned an unexpected response.');
    fail_translation('Translation is temporarily unavailable.', 502);
}

echo json_encode([
    'ok' => true,
    'translation' => html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
], JSON_UNESCAPED_UNICODE);
