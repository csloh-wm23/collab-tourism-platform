<?php
declare(strict_types=1);

function supported_languages(bool $includeAuto = false): array
{
    $languages = [
        'en' => 'English',
        'ms' => 'Bahasa Malaysia',
        'zh' => 'Mandarin Chinese',
        'id' => 'Indonesian',
        'th' => 'Thai',
    ];
    return $includeAuto ? ['auto' => 'Automatic detection'] + $languages : $languages;
}

function supported_scenarios(): array
{
    return ['restaurant', 'hotel', 'transport', 'shopping', 'medical', 'emergency', 'culture'];
}

function clean_text(mixed $value, int $maximum, bool $required = false): string
{
    $text = trim((string) $value);
    if ($required && $text === '') {
        throw new InvalidArgumentException('A required field is empty.');
    }
    if (mb_strlen($text) > $maximum) {
        throw new InvalidArgumentException("Text must not exceed {$maximum} characters.");
    }
    return $text;
}

function clean_language(mixed $value, bool $allowAuto = false): string
{
    $language = (string) $value;
    if (!array_key_exists($language, supported_languages($allowAuto))) {
        throw new InvalidArgumentException('Unsupported language.');
    }
    return $language;
}

function clean_scenario(mixed $value): string
{
    $scenario = (string) $value;
    if (!in_array($scenario, supported_scenarios(), true)) {
        throw new InvalidArgumentException('Unsupported tourism scenario.');
    }
    return $scenario;
}

function clean_confidence(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Invalid confidence score.');
    }
    $confidence = (float) $value;
    if ($confidence < 0 || $confidence > 1) {
        throw new InvalidArgumentException('Invalid confidence score.');
    }
    return $confidence;
}
