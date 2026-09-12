<?php
declare(strict_types=1);

use TourLingo\Translation\GoogleTranslationProvider;
use TourLingo\Translation\PdoTranslationContext;
use TourLingo\Translation\TranslationFailure;
use TourLingo\Translation\TranslationRequest;
use TourLingo\Translation\TranslationService;

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../src/Translation.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

// HTTP boundary only: domain objects handle validation and translation.
function translation_reply(array|JsonSerializable $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    translation_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
}
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    translation_reply(['ok' => false, 'message' => 'Invalid JSON body.'], 400);
}
try {
    $request = TranslationRequest::fromArray($input);
    $service = new TranslationService(
        new GoogleTranslationProvider(trim((string) (getenv('GOOGLE_TRANSLATE_API_KEY') ?: ''))),
        new PdoTranslationContext(static fn(): PDO => database()),
    );
    translation_reply($service->translate($request));
} catch (InvalidArgumentException $e) {
    translation_reply(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (TranslationFailure $e) {
    translation_reply(['ok' => false, 'message' => $e->getMessage()], $e->status);
}
