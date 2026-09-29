@component('mail::message')
# Ticket Submitted

Hi {{ $clientName }}, your support ticket has been created.

**Reference No:** `{{ $refNo }}`

@isset($problem)
**Problem:**
{{ $problem }}
@endisset

@component('mail::button', ['url' => 'https://ticketingportal.jkleonardo.tech/client/rate/' . rawurlencode($refNo)])
Track this Ticket
@endcomponent

If you didn’t submit this request, please reply to this email.

Thanks,
IT Unit
@endcomponent
