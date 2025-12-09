@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 320px; width: 100%;">
<tr>
<td align="center">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="display: block; width: 100%; text-align: center;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
