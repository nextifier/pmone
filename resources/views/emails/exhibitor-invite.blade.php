@component('mail::message')
# You're Invited

Hi {{ $userName }},

You've been invited to manage **{{ $brandName }}** at **{{ $eventTitle }}** on PM One.

@component('mail::button', ['url' => $magicLinkUrl])
Login with Magic Link
@endcomponent

This magic link expires in 15 minutes. After it expires, you can request a new one from the login page.

---

**Alternatively**, you can log in manually with your credentials:

**Email:** {{ $email }}
@if($password)

**Password:** {{ $password }}

Please change your password after logging in.
@endif

[Login with Email & Password]({{ $loginUrl }})

---

If you didn't expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you're having trouble clicking the button, copy and paste this URL into your web browser:
[{{ $magicLinkUrl }}]({{ $magicLinkUrl }})
@endcomponent
@endcomponent
