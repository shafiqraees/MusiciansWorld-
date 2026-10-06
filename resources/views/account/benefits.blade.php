@extends('account.layout', ['title' => 'Benefits', 'current' => 'benefits'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">Benefits</p>
    <h1>What your membership includes</h1>
    <p>Benefits are applied automatically — at checkout for Studio A, when your event sells tickets, and inside DJ School. Only benefits active today are listed.</p>
  </div>
  <a class="btn btn-ghost" href="{{ route('account.membership') }}">Manage membership</a>
</div>

<div class="acct-panel">
  <div class="acct-panel-head">
    <h2>Active on {{ $membership?->plan?->name ?? 'your account' }}</h2>
    @if($membership){!! acct_badge($membership->status) !!}@endif
  </div>
  @if(empty($benefits))
    <p class="acct-empty">{{ $membership ? 'This plan has no recognized benefits right now.' : 'Join a membership to unlock benefits.' }} <a href="/membership">Compare plans</a>.</p>
  @else
    <ul class="acct-list">
      @foreach($benefits as $benefit)
        <li>
          <strong>{{ $benefit['name'] }}</strong>
          <span>{{ $benefit['detail'] }}</span>
          <small>{{ $benefit['key'] }} = {{ $benefit['value'] }}</small>
        </li>
      @endforeach
    </ul>
  @endif
</div>

<div class="acct-panel">
  <h2>How benefits apply</h2>
  <dl class="acct-dl">
    <div><dt>Studio A bookings</dt><dd>Member discounts and included minutes are recognized when you reserve time. The booking receipt shows the discount line.</dd></div>
    <div><dt>Hosting events</dt><dd>Your promoter fee tier sets how much MWCN keeps from each paid ticket. The rest is your proceeds, visible under My Events.</dd></div>
    <div><dt>DJ School</dt><dd>Program pricing and priority enrollment follow your plan. Check DJ School for your registrations.</dd></div>
    <div><dt>Changing plans</dt><dd>Upgrades apply immediately. Cancellations end at the close of the paid period; your account returns to the free Community tier.</dd></div>
  </dl>
</div>

<div class="acct-panel">
  <h2>Compare plans</h2>
  <div class="acct-plan-grid">
    @foreach($plans as $plan)
      <div class="acct-plan {{ $membership?->membership_plan_id === $plan->id ? 'is-current' : '' }}">
        <strong>{{ $plan->name }}</strong>
        <span class="acct-meta">{{ $plan->price_cents ? a_money($plan->price_cents).' / '.($plan->billing_interval ?: 'month') : 'Free' }}</span>
        <ul>
          @forelse($plan->benefits as $benefit)
            <li>{{ $benefit->name }}@if($benefit->pivot->value !== null && $benefit->pivot->value !== '' && $benefit->type !== 'boolean'): {{ $benefit->key === 'promoter_fee_bps' ? \App\Support\Finance::percent((int) $benefit->pivot->value).' platform fee' : $benefit->pivot->value }}@endif</li>
          @empty
            <li>Core MWCN access</li>
          @endforelse
        </ul>
        @if($membership?->membership_plan_id === $plan->id)<span class="acct-status is-mark">Current plan</span>@endif
      </div>
    @endforeach
  </div>
</div>
@endsection
