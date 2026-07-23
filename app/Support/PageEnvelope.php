<?php

namespace App\Support;

/**
 * „Inertia über Blade": Bestehende Blade-Seiten (via <x-app-layout>) rendern
 * statt eines vollständigen HTML-Dokuments nur noch eine Hülle — einen Marker
 * mit base64-kodiertem JSON der Seiten-Fragmente (Header-, Subheader-, Main-
 * HTML). Die BladeToInertia-Middleware erkennt diesen Marker und verwandelt die
 * Antwort in eine Inertia-Antwort (Seitenkomponente „BladePage"). So bleiben
 * alle Controller und Blade-Views unverändert.
 */
final class PageEnvelope
{
    private const PREFIX = '<!--PLANSTACK_PAGE:';
    private const SUFFIX = '-->';

    /** Fragmente in den Marker verpacken (im Layout aufgerufen). */
    public static function wrap(array $fragments): string
    {
        return self::PREFIX . base64_encode(json_encode($fragments)) . self::SUFFIX;
    }

    /** Marker in einem Response-Body finden und die Fragmente zurückgeben (oder null). */
    public static function unwrap(string $content): ?array
    {
        $pattern = '/' . preg_quote(self::PREFIX, '/') . '([A-Za-z0-9+\/=]+)' . preg_quote(self::SUFFIX, '/') . '/';
        if (! preg_match($pattern, $content, $m)) {
            return null;
        }
        $decoded = json_decode(base64_decode($m[1]), true);

        return is_array($decoded) ? $decoded : null;
    }
}
