<div class="acct-panel" style="margin-top:1.25rem">
  <div class="acct-panel-head"><h2>Your codes</h2></div>
  <p class="acct-meta">A code assigned to this account cannot be used by anyone else. A one-time code cannot be used again.</p>
  @if($assignedCoupons->isEmpty() && $couponRedemptions->isEmpty())
    <p class="acct-empty">No coupon or family code is assigned to this account yet.</p>
  @else
    @if($assignedCoupons->isNotEmpty())
      <div class="acct-table-wrap">
        <table class="acct-table">
          <thead><tr><th>Code</th><th>Off</th><th>Who can use it</th><th>Times used</th></tr></thead>
          <tbody>
            @foreach($assignedCoupons as $coupon)
              @php
                $alreadyUsed = $couponRedemptions->where('coupon_id', $coupon->id)->whereIn('status', ['used', 'reserved'])->isNotEmpty()
                  || ($coupon->max_uses === 1 && ($coupon->used_count + $coupon->reserved_count) >= 1);
              @endphp
              <tr>
                <td>{{ $coupon->code }}</td>
                <td>{{ $coupon->percent }}%</td>
                <td>Only {{ $coupon->assigned_email }}</td>
                <td>
                  {{ $coupon->used_count }} used
                  @if($alreadyUsed)
                    · This code cannot be used again
                  @elseif($coupon->max_uses === 1)
                    · One time only
                  @elseif($coupon->once_per_customer)
                    · One use on this account
                  @else
                    · {{ $coupon->max_uses }} total uses
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
    @if($couponRedemptions->isNotEmpty())
      <div class="acct-table-wrap" style="margin-top:1rem">
        <table class="acct-table">
          <thead><tr><th>When</th><th>Code</th><th>Result</th></tr></thead>
          <tbody>
            @foreach($couponRedemptions as $redemption)
              <tr>
                <td>{{ $redemption->created_at->format('m/d/Y H:i') }}</td>
                <td>{{ $redemption->coupon?->code ?? 'Removed code' }}</td>
                <td>
                  @if($redemption->status === 'used' && $redemption->coupon?->isSingleUse())
                    Used once
                  @elseif($redemption->status === 'reserved')
                    Held for checkout
                  @else
                    {{ ucfirst($redemption->status) }}
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif
</div>
