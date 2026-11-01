<h1>Welcome to MusiciansWorld</h1>

<p>Hi {{ $user->name }},</p>

<p>We created your customer account and connected your request to it. Future bookings, DJ School requests, SoundTunnel requests, memberships, events, tickets, payments, and receipts using this email will stay together in your account.</p>

<p><strong>Customer login:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
<p><strong>Username:</strong> {{ $user->email }}</p>
<p><strong>Password:</strong> {{ $temporaryPassword }}</p>

<p>Sign in with your email address as both the username and the password. You can change the password after you sign in.</p>

<p>MusiciansWorld Creative Network</p>
