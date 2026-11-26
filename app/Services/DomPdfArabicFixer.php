<?php

namespace App\Services;

use ArPHP\I18N\Arabic;

/**
 * DomPDF does not shape Arabic/RTL scripts. Pre-process HTML with Ar-PHP
 * so connected glyphs render correctly using DejaVu Sans.
 */
class DomPdfArabicFixer
{
    public function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Replace Arabic segments with presentation-form glyphs DomPDF can draw.
     * Leave Latin / digits / HTML tags untouched.
     */
    public function reshapeHtml(string $html): string
    {
        if ($html === '' || ! $this->containsArabic($html)) {
            return $html;
        }

        $arabic = new Arabic();
        $positions = $arabic->arIdentify($html);

        for ($i = count($positions) - 1; $i >= 0; $i -= 2) {
            $start = $positions[$i - 1];
            $end = $positions[$i];
            $length = $end - $start;
            if ($length <= 0) {
                continue;
            }

            $segment = substr($html, $start, $length);
            // High max_chars avoids mid-HTML wrapping; western digits ($hindo=false).
            $shaped = $arabic->utf8Glyphs($segment, 600, false);
            $html = substr_replace($html, $shaped, $start, $length);
        }

        return $html;
    }

    /**
     * True when contract body (or any HTML) should use right-aligned layout.
     */
    public function prefersRtlLayout(string $text): bool
    {
        if (! $this->containsArabic($text)) {
            return false;
        }

        // Prefer RTL layout when Arabic letters outnumber Latin letters in the body text.
        $plain = strip_tags($text);
        $arabicChars = preg_match_all('/\p{Arabic}/u', $plain) ?: 0;
        $latinChars = preg_match_all('/[A-Za-z]/u', $plain) ?: 0;

        return $arabicChars >= max(1, $latinChars);
    }
}
