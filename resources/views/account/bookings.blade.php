@extends('account.layout', ['title' => 'My Bookings', 'current' => 'bookings'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">My Bookings</p>
    <h1>Studio &amp; rental bookings</h1>
    <p>Every Studio A session and SoundTunnel rental tied to your account. Request a change and the reservation stays confirmed until MWCN updates it.</p>
  </div>
  <a class="btn btn-mark" href="/book">Book Studio A</a>
</div>

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Upcoming</small><strong>{{ $upcoming->count() }}</strong><span>confirmed or pending</span></div>
  <div class="acct-stat"><small>Completed</small><strong>{{ $past->whereNotIn('status', ['cancelled', 'refunded', 'failed'])->count() }}</strong><span>past sessions</span></div>
  <div class="acct-stat"><small>Cancelled</small><strong>{{ $past->whereIn('status', ['cancelled', 'refunded', 'failed'])->count() }}</strong><span>including refunds</span></div>
  <div class="acct-stat is-ok"><small>Total paid</small><strong>{{ a_money($totalSpent) }}</strong><span>across all bookings</span></div>
</div>

<div class="acct-panel">
  <h2>Upcoming</h2>
  @forelse($upcoming as $booking)
    <?php $tz = $booking->resource->timezone ?? 'America/Phoenix'; $requests = $booking->meta['change_requests'] ?? []; ?>
    <div class="acct-item" style="margin-top:.85rem">
      <div class="acct-item-top">
        <div>
          <strong>{{ $booking->resource->name ?? 'Studio A' }} · {{ $booking->reference }}</strong>
          <span>{{ $booking->starts_at->timezone($tz)->format('D, M j, Y · g:i A') }} – {{ $booking->ends_at->timezone($tz)->format('g:i A T') }} · {{ $booking->starts_at->diffInMinutes($booking->ends_at) }} min</span>
        </div>
        <div class="acct-pills">{!! acct_badge($booking->status) !!} {!! acct_badge($booking->payment_status, 'Payment '.a_label($booking->payment_status)) !!}</div>
      </div>
      <dl class="acct-dl">
        <div><dt>Charged</dt><dd>{{ a_money($booking->total_cents) }}@if($booking->discount_cents) <span class="acct-meta">(includes {{ a_money($booking->discount_cents) }} member discount)</span>@endif</dd></div>
        <div><dt>Booked</dt><dd>{{ $booking->created_at->format('M j, Y') }}</dd></div>
        @if($booking->notes)<div><dt>Notes</dt><dd style="white-space:pre-line">{{ $booking->notes }}</dd></div>@endif
      </dl>
      @if($booking->status === 'confirmed')
        <div class="acct-actions">
          <form method="post" action="{{ route('account.bookings.change', $booking) }}">@csrf
            <label>Request a change<textarea class="input" name="message" required maxlength="1000" placeholder="e.g. Move to Saturday 7–9 PM, or add 30 minutes.">{{ old('message') }}</textarea></label>
            <div><button class="btn btn-ghost" type="submit">Send change request</button> <span class="acct-meta">{{ count($requests) ? count($requests).' request(s) already sent.' : 'Your booking stays confirmed until MWCN confirms the change.' }}</span></div>
          </form>
        </div>
      @endif
    </div>
  @empty
    <p class="acct-empty">No upcoming bookings. <a href="/book">Reserve Studio A</a> or <a href="/soundtunnel">request SoundTunnel</a>.</p>
  @endforelse
</div>

<div class="acct-panel">
  <h2>History</h2>
  @if($past->isEmpty())
    <p class="acct-empty">No past bookings yet.</p>
  @else
    <div class="acct-table-wrap">
      <table class="acct-table">
        <thead><tr><th>Reference</th><th>Space</th><th>When</th><th>Status</th><th>Payment</th><th class="is-num">Total</th><th>Receipt</th></tr></thead>
        <tbody>
          @foreach($past as $booking)
            <?php $tz = $booking->resource->timezone ?? 'America/Phoenix'; $receipt = $booking->transactions->whereIn('type', ['studio_booking', 'booking_payment', 'soundtunnel_booking'])->where('status', 'succeeded')->first(); ?>
            <tr>
              <td>{{ $booking->reference }}</td>
              <td>{{ $booking->resource->name ?? '—' }}</td>
              <td>{{ $booking->starts_at->timezone($tz)->format('M j, Y · g:i A') }}</td>
              <td>{!! acct_badge($booking->status) !!}</td>
              <td>{!! acct_badge($booking->payment_status) !!}</td>
              <td class="is-num">{{ a_money($booking->total_cents) }}</td>
              <td>@if($receipt)<a href="{{ route('account.receipts.show', $receipt) }}">View</a>@else —@endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
