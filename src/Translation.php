<?php
declare(strict_types=1);

namespace TourLingo\Translation;

require_once __DIR__ . '/../config/validation.php';

/** A validated, immutable message passed between the translation objects. */
final class TranslationRequest
{
    private function __construct(
        public readonly string $text,
        public readonly string $from,
        public readonly string $to,
        public readonly string $scenario,
    ) {}

    public static function fromArray(array $input): self
    {
        return new self(
            \clean_text($input['text'] ?? '', 500, true),
            \clean_language($input['from'] ?? '', true),
            \clean_language($input['to'] ?? ''),
            \clean_scenario($input['scenario'] ?? 'culture'),
        );
    }
}

final class TranslationFailure extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}

final class TranslationResult implements \JsonSerializable
{
    public function __construct(
        public readonly string $translation,
        public readonly string $detectedLanguage,
        public readonly string $scenario,
        private readonly bool $sameLanguage = false,
        private readonly array $context = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'ok' => true,
            'translation' => $this->translation,
            'detected_language' => $this->detectedLanguage,
            'confidence' => $this->sameLanguage ? 1 : null,
            'confidence_source' => $this->sameLanguage ? 'exact_same_language' : 'not_provided_by_google',
            'alternatives' => $this->context['alternatives'] ?? [],
            'suggestions' => $this->context['suggestions'] ?? [],
            'matched_terms' => $this->context['matched_terms'] ?? [],
            'scenario' => $this->scenario,
        ];
    }
}

interface TranslationProvider
{
    public function translate(TranslationRequest $request): TranslationResult;
}

interface TranslationContext
{
    public function lookup(TranslationRequest $request, string $translation): array;
}

/** Coordinates translation without depending on HTTP, Google or a live database. */
final class TranslationService
{
    public function __construct(
        private readonly TranslationProvider $provider,
        private readonly TranslationContext $context,
    ) {}

    public function translate(TranslationRequest $request): TranslationResult
    {
        if ($request->from !== 'auto' && $request->from === $request->to) {
            return new TranslationResult($request->text, $request->from, $request->scenario, true);
        }
        $result = $this->provider->translate($request);
        try {
            $context = $this->context->lookup($request, $result->translation);
        } catch (\Throwable $e) {
            // Optional context must never discard a successful translation.
            error_log('Translation context lookup failed.');
            $context = [];
        }
        return new TranslationResult($result->translation, $result->detectedLanguage, $request->scenario, false, $context);
    }
}

final class GoogleTranslationProvider implements TranslationProvider
{
    private const CODES = ['en' => 'en', 'ms' => 'ms', 'zh' => 'zh-CN', 'id' => 'id', 'th' => 'th'];

    public function __construct(private readonly string $key) {}

    public function translate(TranslationRequest $request): TranslationResult
    {
        if (trim($this->key) === '') {
            throw new TranslationFailure('Google Cloud Translation is not configured on the server.', 503);
        }
        if (!extension_loaded('curl')) {
            throw new TranslationFailure('PHP cURL extension is required.', 500);
        }
        $body = ['q' => $request->text, 'target' => self::CODES[$request->to], 'format' => 'text'];
        if ($request->from !== 'auto') {
            $body['source'] = self::CODES[$request->from];
        }
        $ch = curl_init('https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($this->key));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8', 'Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || $error !== '') {
            throw new TranslationFailure('Unable to contact Google Cloud Translation.');
        }
        if ($status < 200 || $status >= 300) {
            error_log('Google Cloud Translation HTTP ' . $status);
            throw new TranslationFailure(in_array($status, [400, 401, 403], true)
                ? 'Google Cloud Translation credentials or configuration were rejected.'
                : 'Translation is temporarily unavailable.');
        }
        $data = json_decode($response, true);
        $item = is_array($data) ? ($data['data']['translations'][0] ?? null) : null;
        if (!is_array($item) || !is_string($item['translatedText'] ?? null) || $item['translatedText'] === '') {
            throw new TranslationFailure('Translation is temporarily unavailable.');
        }
        $detected = $request->from;
        if ($detected === 'auto') {
            $raw = $item['detectedSourceLanguage'] ?? 'unknown';
            if (!is_string($raw)) {
                throw new TranslationFailure('Translation is temporarily unavailable.');
            }
            $detected = array_flip(self::CODES)[$raw] ?? $raw;
        }
        return new TranslationResult(
            html_entity_decode($item['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $detected, $request->scenario,
        );
    }
}

final class PdoTranslationContext implements TranslationContext
{
    // Resolve lazily so a database outage does not block the provider result.
    public function __construct(private readonly \Closure $connection) {}

    public function lookup(TranslationRequest $request, string $translation): array
    {
        $db = ($this->connection)();
        $alternatives = [];
        $matchedTerms = [];
        foreach ($db->query('SELECT term,explanation FROM malaysian_terms ORDER BY term')->fetchAll() as $term) {
            if (mb_stripos($request->text, (string) $term['term']) !== false) {
                $matchedTerms[] = (string) $term['term'];
                $alternatives[] = $term['term'] . ': ' . $term['explanation'];
            }
        }
        $phrase = $db->prepare('SELECT translated_text FROM phrase_packs WHERE scenario=? AND language_code=? AND LOWER(source_text)=LOWER(?) ORDER BY id LIMIT 3');
        $phrase->execute([$request->scenario, $request->to, $request->text]);
        foreach ($phrase->fetchAll(\PDO::FETCH_COLUMN) as $candidate) {
            if ($candidate !== $translation && !in_array($candidate, $alternatives, true)) {
                $alternatives[] = (string) $candidate;
            }
        }
        $context = $db->prepare('SELECT source_text,translated_text,suggested_reply FROM phrase_packs WHERE scenario=? AND language_code=? ORDER BY id LIMIT 4');
        $context->execute([$request->scenario, $request->to]);
        return ['alternatives' => $alternatives, 'suggestions' => $context->fetchAll(), 'matched_terms' => $matchedTerms];
    }
}
