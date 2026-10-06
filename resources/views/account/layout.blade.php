<?php
/** @var \App\Models\User $user */
$user = $user ?? auth()->user();
$current = $current ?? '';
page_start(($title ?? 'My account').' — MusiciansWorld', $subtitle ?? 'Your connected MWCN customer portal.');
$counts = [
    'bookings' => $user->bookings()->where('ends_at', '>', now())->whereNotIn('status', ['cancelled', 'refunded', 'failed'])->count(),
    'tickets' => $user->tickets()->whereHas('event', fn ($q) => $q->where('ends_at', '>', now()))->count(),
    'events' => $user->events()->count(),
    'school' => $user->registrations()->whereIn('status', ['active', 'confirmed', 'enrolled', 'pending'])->count(),
];
$membershipName = $user->memberships()->whereIn('status', ['active', 'trialing', 'grace'])->with('plan')->get()->sortByDesc(fn ($m) => (int) ($m->plan?->price_cents ?? 0))->first()?->plan?->name;
$isHost = $counts['events'] > 0 || $user->organizerProfile()->exists() || $user->hasRole('organizer');
$nav = [
    ['Account', [
        ['overview', 'Overview', route('account.dashboard'), null],
        ['profile', 'My Profile', route('account.profile'), null],
        ['membership', 'My Membership', route('account.membership'), null],
        ['benefits', 'Benefits', route('account.benefits'), null],
    ]],
    ['Activity', [
        ['bookings', 'My Bookings', route('account.bookings'), $counts['bookings']],
        ['school', 'DJ School', route('account.school'), $counts['school']],
        ['tickets', 'My Tickets', route('account.tickets'), $counts['tickets']],
        ['events', 'My Events', route('account.events'), $counts['events']],
    ]],
    ['Money', [
        ['payments', 'Payments & Receipts', route('account.payments'), null],
    ]],
];
?>
<section class="section acct">
  <div class="wrap acct-shell">
    <aside class="acct-side">
      <div class="acct-side-user">
        <span class="kicker">Customer portal</span>
        <strong>{{ $user->name }}</strong>
        <span>{{ $user->email }}</span>
        <div class="acct-pills">
          @if($membershipName)<span class="pill">{{ $membershipName }}</span>@endif
          @if($isHost)<span class="pill">Promoter</span>@endif
        </div>
      </div>
      <nav aria-label="Account sections">
        @foreach($nav as [$group, $links])
          <small>{{ $group }}</small>
          @foreach($links as [$key, $label, $href, $count])
            <a href="{{ $href }}" @class(['is-active' => $current === $key]) @if($current === $key) aria-current="page" @endif>
              <span>{{ $label }}</span>
              @if($count)<span class="acct-count">{{ $count }}</span>@endif
            </a>
          @endforeach
        @endforeach
      </nav>
      <div class="acct-side-foot">
        @if($isHost)
          <a class="btn btn-mark" href="{{ route('organizer.events.create') }}">Host an event</a>
        @else
          <a class="btn btn-ghost" href="{{ route('organizer.events.create') }}">Become a promoter</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost" type="submit">Sign out</button></form>
      </div>
    </aside>

    <div class="acct-main">
      @if(session('success'))<p class="acct-note is-ok">{{ session('success') }}</p>@endif
      @if($errors->any())<div class="acct-note is-bad">{{ $errors->first() }}</div>@endif
      @if(!$user->hasVerifiedEmail())
        <form method="post" action="{{ route('verification.send') }}" class="acct-note is-warn">@csrf
          <span>Verify {{ $user->email }} so receipts and booking updates can reach you.</span>
          <button class="acct-text-btn" type="submit">Resend verification email</button>
        </form>
      @endif
      @yield('content')
    </div>
  </div>
</section>
<?php page_end(); ?>
