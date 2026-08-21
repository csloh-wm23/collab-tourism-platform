<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once __DIR__ . '/../config/env.php';

function fail_json(string $message, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail_json('Method not allowed.', 405);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    fail_json('Invalid JSON body.', 400);
}

$text = trim((string)($input['text'] ?? ''));
$lang = (string)($input['language'] ?? '');

$voices = [
    'en' => ['locale' => 'en-US', 'voice' => 'en-US-JennyNeural'],
    'ms' => ['locale' => 'ms-MY', 'voice' => 'ms-MY-YasminNeural'],
    'zh' => ['locale' => 'zh-CN', 'voice' => 'zh-CN-XiaoxiaoNeural'],
    'ta' => ['locale' => 'ta-MY', 'voice' => 'ta-MY-KaniNeural'],
];

if ($text === '' || mb_strlen($text) > 500) {
    fail_json('Speech text must contain 1 to 500 characters.', 422);
}
if (!isset($voices[$lang])) {
    fail_json('Unsupported speech language.', 422);
}

$key = trim((string)(getenv('AZURE_SPEECH_KEY') ?: ''));
$region = trim((string)(getenv('AZURE_SPEECH_REGION') ?: ''));

if ($key === '' || $region === '') {
    fail_json('Azure Speech is not configured on the server.', 503);
}
if (!extension_loaded('curl')) {
    fail_json('PHP cURL extension is required.', 500);
}

$choice = $voices[$lang];
$escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$ssml = "<speak version='1.0' xml:lang='{$choice['locale']}'><voice xml:lang='{$choice['locale']}' name='{$choice['voice']}'>{$escaped}</voice></speak>";
$url = 'https://' . rawurlencode($region) . '.tts.speech.microsoft.com/cognitiveservices/v1';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $ssml,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Ocp-Apim-Subscription-Key: ' . $key,
        'Content-Type: application/ssml+xml',
        'X-Microsoft-OutputFormat: audio-16khz-32kbitrate-mono-mp3',
        'User-Agent: JomCommunicate'
    ],
]);

$audio = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($audio === false || $error !== '') {
    fail_json('Unable to contact Azure Speech.', 502);
}
if ($status < 200 || $status >= 300) {
    error_log('Azure Speech HTTP ' . $status . ': ' . substr((string)$audio, 0, 500));
    fail_json('Azure Speech rejected the request. Check the key, region and quota.', 502);
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen((string)$audio));
echo $audio;
