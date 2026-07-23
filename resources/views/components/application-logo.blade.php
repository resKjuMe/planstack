{{-- Planstack-Bildmarke (public/images/planstack-logo.png). Die übergebene
     Klasse (z. B. h-16 w-auto) bestimmt die Größe; das Bild ist quadratisch. --}}
<img src="{{ asset('images/planstack-logo.png') }}" alt="Planstack"
     {{ $attributes->merge(['class' => 'rounded-md']) }} />
