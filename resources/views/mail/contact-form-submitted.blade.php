@component('mail::message')
# New Support Request

You have received a new support request from the website contact form.

**Name:** {{ $contactForm->name }}

**Email:** {{ $contactForm->email }}

**Subject:** {{ $contactForm->subject }}

**Message:**
{{ $contactForm->message }}

@component('mail::button', ['url' => config('app.url') . '/admin/contact-forms/' . $contactForm->id])
View in Admin Panel
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent