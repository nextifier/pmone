@component('mail::message')
# New Response - {{ $form->title }}

You have received a new response for **{{ $form->title }}**.

@component('mail::panel')
@if($respondentEmail)
**Email:**
{{ $respondentEmail }}

@endif
@foreach($answers as $answer)
**{{ $answer['label'] }}:**
{{ $answer['value'] }}

@endforeach
@endcomponent

@if($respondentEmail)
<div style="margin-bottom: -8px;">
@component('mail::button', ['url' => 'mailto:' . $respondentEmail])
Reply via Email
@endcomponent
</div>
@endif

@component('mail::button', ['url' => config('app.frontend_url') . '/forms/' . $form->slug . '/responses'])
View Responses
@endcomponent

@component('mail::subcopy')
Submitted at: {{ $submittedAt->format('F j, Y \a\t g:i A T') }}@if($ipAddress) from {{ $ipAddress }}@endif
@endcomponent

@endcomponent
