@component('mail::message')
# New User Reply

**{{ $user->name }}** has replied to a support request.

**Subject:** {{ $contactForm->subject }}  
**Ticket ID:** {{ $contactForm->id }}  
**Status:** {{ ucfirst($contactForm->status) }}

## Message:
{{ $replyMessage }}

@component('mail::button', ['url' => route('admin.contact-forms.show', $contactForm->id)])
View Full Conversation
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent