{{--
    „Inertia über Blade": <x-app-layout> rendert kein vollständiges HTML-Dokument
    mehr, sondern nur eine Hülle mit den Seiten-Fragmenten (Header-, Subheader-,
    Main-HTML). Die BladeToInertia-Middleware erkennt den Marker (PageEnvelope)
    und macht daraus eine Inertia-Antwort der Komponente „BladePage"; das
    persistente Grundgerüst (Wrapper/Navi/Glocke) liefert app-root + resources/js.

    Bewusst OHNE führende Ausgabe/Leerzeichen: Der Marker soll den Body dominieren.
--}}
@php
    echo \App\Support\PageEnvelope::wrap([
        'title' => config('app.name', 'Planstack'),
        'header' => isset($header) ? (string) $header : null,
        'subheader' => isset($subheader) ? (string) $subheader : null,
        'main' => (string) $slot,
    ]);
@endphp
