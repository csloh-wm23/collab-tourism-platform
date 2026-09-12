<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/validation.php';
require_once __DIR__ . '/../config/auth.php';
$viewer = current_user();
if (!$viewer || $viewer['status'] !== 'active') {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Sign in with an active account to use travel guides.']));
}
function assistance_translate_batch(array $texts, string $source, string $target): array
{
    if (!$texts || $source === $target) {
        return $texts;
    }
    $codes = ['en' => 'en', 'ms' => 'ms', 'zh' => 'zh-CN', 'id' => 'id', 'th' => 'th'];
    $key = trim((string) (getenv('GOOGLE_TRANSLATE_API_KEY') ?: ''));
    if ($key === '' || !extension_loaded('curl')) {
        throw new RuntimeException(
            'The selected language pack must be loaded online once before it can be used offline.',
        );
    }
    $ch = curl_init(
        'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key),
    );
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(
            [
                'q' => array_values($texts),
                'source' => $codes[$source],
                'target' => $codes[$target],
                'format' => 'text',
            ],
            JSON_UNESCAPED_UNICODE,
        ),
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
    if ($response === false || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('Could not prepare the selected language pack.');
    }
    $rows = json_decode((string) $response, true)['data']['translations'] ?? [];
    $translated = [];
    foreach ($rows as $row) {
        $translated[] = html_entity_decode(
            (string) ($row['translatedText'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
    }
    if (count($translated) !== count($texts)) {
        throw new RuntimeException('The selected language pack was incomplete.');
    }
    return $translated;
}
// Look up the requested destination/situation/language pack. If absent, use the
// Malaysia Malay pack and translate it when needed. Return the resolved destination
// so the interface can show that fallback rather than imply destination-specific data.
try {
    $scenario = clean_scenario($_GET['scenario'] ?? 'restaurant');
    $destination = clean_text($_GET['destination'] ?? 'Malaysia', 120, true);
    $language = clean_language($_GET['language'] ?? 'ms');
    $db = database();
    $stmt = $db->prepare(
        'SELECT id,destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip,is_offline_ready FROM phrase_packs WHERE destination=? AND scenario=? AND language_code=? ORDER BY id',
    );
    $stmt->execute([$destination, $scenario, $language]);
    $phrases = $stmt->fetchAll();
    $resolved = $destination;
    if (!$phrases) {
        $fallback = $db->prepare(
            'SELECT id,destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip,is_offline_ready FROM phrase_packs WHERE destination="Malaysia" AND scenario=? AND language_code="ms" ORDER BY id',
        );
        $fallback->execute([$scenario]);
        $phrases = $fallback->fetchAll();
        $resolved = 'Malaysia';
        if ($language !== 'ms' && $phrases) {
            $translated = assistance_translate_batch(
                array_column($phrases, 'source_text'),
                'en',
                $language,
            );
            $replyIndexes = [];
            $replyTexts = [];
            foreach ($phrases as $index => $phrase) {
                if ((string) $phrase['suggested_reply'] !== '') {
                    $replyIndexes[] = $index;
                    $replyTexts[] = (string) $phrase['suggested_reply'];
                }
            }
            $translatedReplies = assistance_translate_batch($replyTexts, 'ms', $language);
            foreach ($phrases as $index => &$phrase) {
                $phrase['translated_text'] = $translated[$index];
                $phrase['language_code'] = $language;
            }
            $phrase = null;
            foreach ($replyIndexes as $position => $index) {
                $phrases[$index]['suggested_reply'] = $translatedReplies[$position];
            }
        }
    }
    echo json_encode(
        [
            'ok' => true,
            'requested_destination' => $destination,
            'resolved_destination' => $resolved,
            'fallback_used' => $resolved !== $destination,
            'phrases' => $phrases,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Assistance: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Assistance phrases are unavailable.']);
}
