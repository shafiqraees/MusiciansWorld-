@extends('account.layout', ['title' => 'My Membership', 'current' => 'membership'])

<?php $renews = $membership?->cancelled_at ? null : $membership?->current_period_end; $isPaid = (int) ($membership?->plan?->price_cents ?? 0) > 0; ?>

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">My Membership</p>
    <h1>{{ $membership?->plan?->name ?? 'No active membership' }}</h1>
    <p>Your membership unlocks studio pricing, promoter fee tiers and DJ School perks. Changing plans never removes your account history.</p>
  </div>
  @if($membership){!! acct_badge($membership->cancelled_at ? 'pending' : $membership->status, $membership->cancelled_at ? 'Ending '.($membership->current_period_end?->format('M j') ?? 'soon') : null) !!}@endif
</div>

@if($membership?->cancelled_at && $isPaid)
  <form method="post" action="{{ route('account.membership.resume') }}" class="acct-note is-warn">@csrf
    <span>Your {{ $membership->plan->name }} is scheduled to end on <strong>{{ $membership->current_period_end?->format('F j, Y') ?? 'the end of this period' }}</strong>. You keep every benefit until then, and your account moves to the free Community tier afterwards — nothing is deleted.</span>
    <button class="acct-text-btn" type="submit">Keep my membership</button>
  </form>
@endif

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Plan</small><strong>{{ $membership?->plan?->name ?? '—' }}</strong><span>{{ $membership?->plan ? ($membership->plan->price_cents ? a_money($membership->plan->price_cents).' / '.($membership->plan->billing_interval ?: 'month') : 'Free') : 'Pick a plan below' }}</span></div>
  <div class="acct-stat"><small>Status</small><strong>{{ a_label($membership?->status ?? 'none') }}</strong><span>{{ $membership?->cancelled_at ? 'Cancellation scheduled' : 'In good standing' }}</span></div>
  <div class="acct-stat"><small>{{ $membership?->cancelled_at ? 'Ends' : 'Renews' }}</small><strong>{{ $membership?->current_period_end?->format('M j, Y') ?? '—' }}</strong><span>{{ $membership?->current_period_start ? 'Period started '.$membership->current_period_start->format('M j') : 'No billing period' }}</span></div>
  <div class="acct-stat"><small>Benefits</small><strong>{{ count($benefits) }}</strong><span><a href="{{ route('account.benefits') }}">See what's included</a></span></div>
</div>

<div class="acct-grid-2">
  <div class="acct-panel">
    <h2>Current plan</h2>
    @if($membership)
      <dl class="acct-dl">
        <div><dt>Plan</dt><dd>{{ $membership->plan?->name }}</dd></div>
        <div><dt>Price</dt><dd>{{ $membership->plan?->price_cents ? a_money($membership->plan->price_cents).' per '.($membership->plan->billing_interval ?: 'month') : 'Free' }}</dd></div>
        <div><dt>Status</dt><dd>{{ $membership->cancelled_at ? 'Active until '.($membership->current_period_end?->format('M j, Y') ?? 'period end').' (cancellation scheduled)' : a_label($membership->status) }}</dd></div>
        <div><dt>{{ $membership->cancelled_at ? 'Next charge' : 'Renews' }}</dt><dd>{{ $membership->cancelled_at ? 'None — your plan will not renew' : ($renews?->format('M j, Y') ?? 'No renewal date') }}</dd></div>
        <div><dt>Billing</dt><dd>{{ $membership->plan?->price_cents ? ($membership->stripe_subscription_id ? 'Card on file, billed automatically through Stripe' : 'Billed by the MWCN desk') : 'No charge' }}</dd></div>
        <div><dt>Member since</dt><dd>{{ $membership->created_at->format('M j, Y') }}</dd></div>
      </dl>
      @if($membership->plan?->description)<p class="acct-lead">{{ $membership->plan->description }}</p>@endif

      <div class="acct-actions">
        @if($isPaid && !$membership->cancelled_at)
          <form method="post" action="{{ route('account.membership.cancel') }}">@csrf
            <label class="acct-confirm"><input type="checkbox" name="confirm" value="1" required> I understand my {{ $membership->plan->name }} benefits continue until {{ $membership->current_period_end?->format('M j, Y') ?? 'the end of the current period' }}, then my account returns to the free Community tier. My history is kept.</label>
            <button class="btn btn-ghost" type="submit">Cancel at period end</button>
          </form>
        @elseif($isPaid && $membership->cancelled_at)
          <p class="acct-lead">Cancellation requested on {{ $membership->cancelled_at->format('M j, Y') }}. No further charges will be made; your benefits continue until {{ $membership->current_period_end?->format('M j, Y') ?? 'the end of the current period' }}. Use <strong>Keep my membership</strong> above if you change your mind.</p>
        @else
          <p class="acct-lead">Community membership is free and stays with your account. Upgrade any time for lower promoter fees and studio perks.</p>
          <a class="btn btn-mark" href="/membership">Compare paid plans</a>
        @endif
      </div>
    @else
      <p class="acct-empty">You don't have an active membership. Join the Community tier for free or choose a paid plan.</p>
      <div class="acct-actions"><a class="btn btn-mark" href="/membership">Choose a plan</a></div>
    @endif
  </div>

  <div class="acct-panel">
    <h2>Available plans</h2>
    <div class="acct-plan-grid">
      @foreach($plans as $plan)
        <div class="acct-plan {{ $membership?->membership_plan_id === $plan->id ? 'is-current' : '' }}">
          <strong>{{ $plan->name }}</strong>
          <span class="acct-meta">{{ $plan->price_cents ? a_money($plan->price_cents).' / '.($plan->billing_interval ?: 'month') : 'Free' }}</span>
          @if($plan->description)<p class="acct-meta" style="margin:0;font-size:.88rem">{{ $plan->description }}</p>@endif
          @if($membership?->membership_plan_id === $plan->id)
            <span class="acct-status is-mark">Current plan</span>
          @else
            <a class="btn btn-ghost" href="/membership#plans" style="margin-top:.4rem">{{ ($plan->price_cents ?? 0) > ($membership?->plan?->price_cents ?? 0) ? 'Upgrade' : 'View' }}</a>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</div>

<div class="acct-panel">
  <h2>Membership history</h2>
  @if($history->isEmpty())
    <p class="acct-empty">No membership records yet.</p>
  @else
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Plan</th><th>Status</th><th>Period</th><th>Started</th><th>Cancelled</th><th class="is-num">Price</th></tr></thead>
        <tbody>
          @foreach($history as $row)
            <tr>
              <td>{{ $row->plan?->name ?? '—' }}</td>
              <td>{!! acct_badge($row->status) !!}</td>
              <td>{{ $row->current_period_start?->format('M j, Y') ?? '—' }} → {{ $row->current_period_end?->format('M j, Y') ?? '—' }}</td>
              <td>{{ $row->created_at->format('M j, Y') }}</td>
              <td>{{ $row->cancelled_at?->format('M j, Y') ?? '—' }}</td>
              <td class="is-num">{{ $row->plan?->price_cents ? a_money($row->plan->price_cents) : 'Free' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
