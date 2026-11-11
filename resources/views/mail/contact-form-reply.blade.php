@component('mail::message')
# Response to Your Support Request

Dear {{ $contactForm->name }},

Thank you for contacting our support team. Below is our response to your inquiry about "{{ $contactForm->subject }}":

---

{!! nl2br(e($replyMessage)) !!}

---

If you have any further questions or need additional assistance, please don't hesitate to reply to this email or submit another inquiry through our support form.

@component('mail::button', ['url' => config('app.url')])
Visit Our Website
@endcomponent

Thank you for your patience,<br>
{{ config('app.name') }} Support Team
@endcomponent