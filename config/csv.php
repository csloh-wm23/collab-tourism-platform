<?php
declare(strict_types=1);

/** Quote formula-like text as data before CSV serialization (not just CSV escaping). */
function safe_csv_row($stream, array $fields): int|false
{
    $fields = array_map(static function ($value) {
        if (is_string($value) && preg_match('/^[\s\x00-\x1f]*[=+@\-＝＋－＠]|^[\t\r\n]/u', $value)) {
            return "'" . $value;
        }
        return $value;
    }, $fields);
    return fputcsv($stream, $fields, ',', '"', '');
}
