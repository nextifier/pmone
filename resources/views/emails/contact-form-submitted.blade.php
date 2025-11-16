@component('mail::message')
# New Contact Form Submission - {{ $project->name }}

You have received a new contact form submission from your website.

@component('mail::panel')
@foreach($formData as $field => $value)
@if(is_string($value) || is_numeric($value))
**{{ ucwords(str_replace('_', ' ', $field)) }}:**
{{ $value }}

@endif
@endforeach
@endcomponent

**Submission Details:**
- Submitted at: {{ $submittedAt->format('F j, Y \a\t g:i A T') }}
- IP Address: {{ $ipAddress ?? 'N/A' }}

@component('mail::button', ['url' => config('app.frontend_url') . '/inbox'])
View in Inbox
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
