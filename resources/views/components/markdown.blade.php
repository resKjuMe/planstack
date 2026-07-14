@props(['content' => null])

{{-- Renders trusted-ish user text as Markdown. Raw HTML in the source is
     stripped and unsafe links are dropped, so a description can't inject markup
     or javascript: URLs. Styling comes from the global .md-content rules. --}}
@if (filled($content))
    <div {{ $attributes->merge(['class' => 'md-content']) }}>
        {!! \Illuminate\Support\Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Keep single newlines as line breaks (authors type them expecting
            // a break, like the old whitespace-pre-line rendering) instead of
            // CommonMark's default of collapsing them into a space.
            'renderer' => ['soft_break' => "<br>\n"],
        ]) !!}
    </div>
@endif
