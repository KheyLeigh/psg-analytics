<?php
declare(strict_types=1);
// php/core/Validator.php
// Nettoie les entrées utilisateur (query string, paramètres de route) par liste blanche.
final class Validator
{
    public static function int(mixed $v, int $min, int $max, int $default): int
    {
        if (!is_numeric($v)) {
            return $default;
        }
        $n = (int) $v;
        return max($min, min($max, $n));
    }

    /** @param array<int,string> $allowed */
    public static function inList(mixed $v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? (string) $v : $default;
    }

    public static function string(mixed $v, int $maxLen): string
    {
        return mb_substr(is_string($v) ? trim($v) : '', 0, $maxLen);
    }
}
