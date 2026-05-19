@php
    $bg = $color ?? '#52525B';
    $fg = $textColor ?? '#FFFFFF';
    $label = $label ?? strtoupper($channel ?? '?');
    // Auto-tune font size based on label length to keep badge proportional.
    $fontSize = match (true) {
        strlen($label) <= 3 => 13,
        strlen($label) <= 5 => 11,
        strlen($label) <= 7 => 9,
        default => 8,
    };
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 30" width="56" height="18" style="vertical-align: middle;">
    <rect width="100" height="30" rx="4" fill="{{ $bg }}"/>
    <text x="50" y="20" text-anchor="middle" fill="{{ $fg }}" font-family="Helvetica, Arial, sans-serif" font-size="{{ $fontSize }}" font-weight="700" letter-spacing="0.5">{{ $label }}</text>
</svg>
