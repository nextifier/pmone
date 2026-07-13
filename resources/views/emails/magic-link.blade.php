@component('mail::message')
# Login to {{ config('app.name') }}

You requested a magic link to log in to your {{ config('app.name') }} account.

@component('mail::button', ['url' => $loginUrl])
Log In to {{ config('app.name') }}
@endcomponent

This link will expire at {{ $expiresAt->format('F j, Y \a\t g:i A T') }}.

If you didn't request this login link, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you're having trouble clicking the "Log In to {{ config('app.name') }}" button, copy and paste the URL below into your web browser:
[{{ $loginUrl }}]({{ $loginUrl }})
@endcomponent
@endcomponent
