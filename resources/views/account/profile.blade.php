@extends('account.layout', ['title' => 'My Profile', 'current' => 'profile'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">My Profile</p>
    <h1>Personal details</h1>
    <p>Keep your contact information current so booking confirmations, ticket QR codes and receipts reach you.</p>
  </div>
</div>

<div class="acct-grid-2">
  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Account</h2>{!! acct_badge($user->status) !!}</div>
    <dl class="acct-dl">
      <div><dt>Account ID</dt><dd>#{{ $user->id }}</dd></div>
      <div><dt>Email</dt><dd>{{ $user->email }} {!! $user->hasVerifiedEmail() ? acct_badge('verified', 'Verified') : acct_badge('pending', 'Unverified') !!}</dd></div>
      <div><dt>Member since</dt><dd>{{ $user->created_at->format('M j, Y') }}</dd></div>
      <div><dt>Last sign in</dt><dd>{{ $user->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
      <div><dt>Account roles</dt><dd>{{ $user->roles->pluck('label')->join(', ') ?: 'Customer' }}</dd></div>
      <div><dt>Account type</dt><dd>{{ $user->accountTypeLabel() }}</dd></div>
    </dl>
  </div>

  <div class="acct-panel">
    <h2>Security</h2>
    <p class="acct-lead">Passwords are changed from the sign-in screen: sign out, choose <strong>Forgot password</strong>, and we'll email a secure reset link to {{ $user->email }}. Your bookings, tickets and receipts stay attached to this account.</p>
  </div>
</div>

<div class="acct-panel">
  <h2>Contact details &amp; preferences</h2>
  <form method="post" action="{{ route('account.profile.update') }}" class="acct-form">
    @csrf @method('PATCH')
    <label>Full name<input class="input" name="name" value="{{ old('name', $user->name) }}" required></label>
    <label>Email<input class="input" value="{{ $user->email }}" readonly></label>
    <label>Phone<input class="input" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="(480) 555-0100"></label>
    <label>City<input class="input" name="city" value="{{ old('city', $user->city) }}"></label>
    <label>State<input class="input" name="state" value="{{ old('state', $user->state) }}"></label>
    <label>Postal code<input class="input" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}"></label>
    <fieldset class="acct-checks">
      <legend>Communication preferences</legend>
      <label><input type="checkbox" name="booking_updates" value="1" @checked(old('booking_updates', $prefs['booking_updates'] ?? true))> Booking confirmations, reminders and change updates</label>
      <label><input type="checkbox" name="event_updates" value="1" @checked(old('event_updates', $prefs['event_updates'] ?? true))> Ticket delivery and event changes for events I attend or host</label>
      <label><input type="checkbox" name="school_updates" value="1" @checked(old('school_updates', $prefs['school_updates'] ?? true))> DJ School schedules, instructor notes and program news</label>
      <label><input type="checkbox" name="marketing" value="1" @checked(old('marketing', $prefs['marketing'] ?? false))> Member news, offers and MWCN announcements</label>
    </fieldset>
    <button class="btn btn-mark" type="submit">Save profile</button>
  </form>
</div>
@endsection
