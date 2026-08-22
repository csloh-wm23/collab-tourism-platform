<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once __DIR__ . '/../config/auth.php';

function fail_json(string $message, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail_json('Method not allowed.', 405);
}

// A small per-session rolling limit is appropriate for this student project
// and also covers guests without collecting their conversation text.
$now = time();
$recent = array_values(array_filter(
    is_array($_SESSION['speech_requests'] ?? null) ? $_SESSION['speech_requests'] : [],
    static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - 60
));
if (count($recent) >= 10) {
    header('Retry-After: 60');
    fail_json('Too many speech requests. Please wait a minute and try again.', 429);
}
$recent[] = $now;
$_SESSION['speech_requests'] = $recent;

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    fail_json('Invalid JSON body.', 400);
}

$text = trim((string)($input['text'] ?? ''));
$lang = (string)($input['language'] ?? '');

$voices = [
    'en' => ['languageCode' => 'en-US', 'name' => 'en-US-Standard-C'],
    // Malay translations are intentionally read by an Indonesian Google voice.
    'ms' => ['languageCode' => 'id-ID', 'name' => 'id-ID-Standard-A'],
    'zh' => ['languageCode' => 'cmn-CN', 'name' => 'cmn-CN-Standard-A'],
    'ta' => ['languageCode' => 'ta-IN', 'name' => 'ta-IN-Standard-A'],
];

if ($text === '' || mb_strlen($text) > 500) {
    fail_json('Speech text must contain 1 to 500 characters.', 422);
}
if (!isset($voices[$lang])) {
    fail_json('Unsupported speech language.', 422);
}

$key = trim((string)(getenv('GOOGLE_TRANSLATE_API_KEY') ?: ''));
if ($key === '') {
    fail_json('Google Cloud Text-to-Speech is not configured on the server.', 503);
}
if (!extension_loaded('curl')) {
    fail_json('PHP cURL extension is required.', 500);
}

$choice = $voices[$lang];
$url = 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . rawurlencode($key);
$body = json_encode([
    'input' => ['text' => $text],
    'voice' => $choice,
    'audioConfig' => ['audioEncoding' => 'MP3'],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=UTF-8',
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $error !== '') {
    fail_json('Unable to contact Google Cloud Text-to-Speech.', 502);
}
if ($status < 200 || $status >= 300) {
    error_log('Google Cloud Text-to-Speech returned HTTP ' . $status);
    fail_json(
        in_array($status, [400, 401, 403], true)
            ? 'Google Cloud Text-to-Speech credentials or configuration were rejected.'
            : 'Speech generation is temporarily unavailable.',
        502
    );
}

$data = json_decode((string)$response, true);
$encodedAudio = $data['audioContent'] ?? null;
if (!is_string($encodedAudio) || $encodedAudio === '') {
    error_log('Google Cloud Text-to-Speech returned an unexpected response.');
    fail_json('Speech generation is temporarily unavailable.', 502);
}
$audio = base64_decode($encodedAudio, true);
if ($audio === false || $audio === '') {
    error_log('Google Cloud Text-to-Speech returned invalid audio data.');
    fail_json('Speech generation is temporarily unavailable.', 502);
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen((string)$audio));
echo $audio;
