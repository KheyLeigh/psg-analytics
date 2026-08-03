<?php
declare(strict_types=1);
// Génère un PDF 1.4 minimal (une page, police Helvetica) sans dépendance externe.
final class PdfExporter
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const MARGIN_LEFT = 50;
    private const TOP = 792;

    /**
     * @param array<int, array{heading?: string, lines?: array<int, string>}> $sections
     */
    public static function simpleReport(string $title, array $sections): string
    {
        $stream = self::buildContentStream($title, $sections);
        return self::assemble($stream);
    }

    private static function buildContentStream(string $title, array $sections): string
    {
        $lines = ['BT', '/F1 16 Tf', sprintf('1 0 0 1 %d %d Tm', self::MARGIN_LEFT, self::TOP)];
        $lines[] = '(' . self::escapeText($title) . ') Tj';
        $lines[] = '/F1 11 Tf';

        foreach ($sections as $section) {
            $heading = (string) ($section['heading'] ?? '');
            if ($heading !== '') {
                $lines[] = '0 -24 Td';
                $lines[] = '(' . self::escapeText($heading) . ') Tj';
            }
            foreach ((array) ($section['lines'] ?? []) as $line) {
                $lines[] = '0 -14 Td';
                $lines[] = '(' . self::escapeText((string) $line) . ') Tj';
            }
        }

        $lines[] = 'ET';
        return implode("\n", $lines);
    }

    // Échappe les caractères réservés d'une chaîne littérale PDF : ( ) \
    private static function escapeText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function assemble(string $contentStream): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> '
                . '/MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($contentStream) . " >>\nstream\n{$contentStream}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $total = count($objects) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }
}
