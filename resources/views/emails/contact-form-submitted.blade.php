@component('mail::message')
# {{ $subject }}@if($project && $project->name) - {{ $project->name }}@endif

You have received a new form submission from **{{ $project->name ?? 'your website' }}**.

@component('mail::panel')
@foreach($formData as $field => $value)
@if(is_string($value) || is_numeric($value))
@if($field === 'message')
**{{ ucwords(str_replace('_', ' ', $field)) }}:**
<br>{{ $value }}

@else
**{{ ucwords(str_replace('_', ' ', $field)) }}:**
{{ $value }}

@endif
@endif
@endforeach
@endcomponent

@if(!empty($formData['email']))
<div style="margin-bottom: -8px;">
@component('mail::button', ['url' => 'mailto:' . $formData['email']])
Reply via Email
@endcomponent
</div>
@endif

@if(!empty($formData['phone']))
@php
$phone = preg_replace('/[^0-9+]/', '', $formData['phone']);
if (str_starts_with($phone, '0')) {
    $phone = '62' . substr($phone, 1);
} elseif (!str_starts_with($phone, '+') && !str_starts_with($phone, '62')) {
    $phone = '62' . $phone;
}
$phone = ltrim($phone, '+');
@endphp
<div style="margin-bottom: -8px;">
@component('mail::button', ['url' => 'https://wa.me/' . $phone])
Reply via WhatsApp
@endcomponent
</div>
@endif

@component('mail::button', ['url' => config('app.frontend_url') . '/inbox'])
View in Inbox
@endcomponent

@component('mail::subcopy')
Submitted at: {{ $submittedAt->format('F j, Y \a\t g:i A T') }}
@endcomponent

@endcomponent
