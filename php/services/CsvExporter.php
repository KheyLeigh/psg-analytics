<?php
declare(strict_types=1);
// Génère un export CSV (BOM UTF-8, séparateur point-virgule) depuis en-têtes et lignes.
final class CsvExporter
{
    public static function fromRows(array $headers, array $rows): string
    {
        $out = "\xEF\xBB\xBF"; // BOM UTF-8
        $out .= self::line($headers);
        foreach ($rows as $row) {
            $out .= self::line($row);
        }
        return $out;
    }

    private static function line(array $cells): string
    {
        $escaped = array_map(static function ($cell): string {
            $value = (string) $cell;
            if (str_contains($value, '"') || str_contains($value, ';') || str_contains($value, "\n")) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $cells);
        return implode(';', $escaped) . "\r\n";
    }
}
