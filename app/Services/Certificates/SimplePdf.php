<?php

namespace App\Services\Certificates;

/**
 * Minimal single-page landscape PDF writer (no third-party deps).
 */
final class SimplePdf
{
    /**
     * @param  list<array{text: string, x: float, y: float, size?: float}>  $lines
     */
    public static function landscapeA4(array $lines): string
    {
        // A4 landscape in points: 842 x 595
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';

        $content = "BT\n";
        foreach ($lines as $line) {
            $size = $line['size'] ?? 14;
            $text = self::escape($line['text']);
            $content .= "/F1 {$size} Tf\n";
            $content .= sprintf("%.2F %.2F Td\n(%s) Tj\n", $line['x'], $line['y'], $text);
            $content .= sprintf("%.2F %.2F Td\n", -$line['x'], -$line['y']);
        }
        $content .= "ET";

        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        // Helvetica core font: strip non-latin1 safely for certificate names.
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }
}
