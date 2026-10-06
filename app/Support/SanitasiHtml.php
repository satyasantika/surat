<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitasi isi kaya naskah (BR-08): daftar tag putih minimal, tanpa atribut gaya/event, tanpa gambar.
 * Dipakai saat simpan dan sekali lagi saat render (defense in depth).
 */
class SanitasiHtml
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function bersihkan(?string $html): string
    {
        return $html === null || trim($html) === '' ? '' : self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('b')
                ->allowElement('em')->allowElement('i')->allowElement('u')
                ->allowElement('ul')->allowElement('ol')->allowElement('li')
                ->allowElement('h2')->allowElement('h3')->allowElement('h4')->allowElement('blockquote')
                ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
                ->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan'])
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'mailto'])
                ->allowRelativeLinks(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->dropElement('script')->dropElement('style')
                ->withMaxInputLength(200_000)
        );
    }
}
