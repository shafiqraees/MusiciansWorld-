<h1>Welcome to MusiciansWorld</h1>

<p>Hi {{ $user->name }},</p>

<p>We created your customer account and connected your request to it. Future bookings, DJ School requests, SoundTunnel requests, memberships, events, tickets, payments, and receipts using this email will stay together in your account.</p>

<p><strong>Customer login:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
<p><strong>Email:</strong> {{ $user->email }}</p>
<p><strong>Temporary password:</strong> {{ $temporaryPassword }}</p>

<p>Please sign in and change your password. You can also use “Forgot your password?” on the login page if you prefer to set a new one by email.</p>

<p>MusiciansWorld Creative Network</p>
