<?php page_start('Coupons & rates — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Finance</p>
      <h2>Coupons &amp; rates</h2>
      <p>Set the Studio A hourly rates, create coupon and family codes, and see who used them. A first-time client rate cannot be combined with a coupon.</p>
    </div>
    <div class="a-page-actions"><button class="a-btn primary" type="button" data-modal-open="create-coupon"><svg><use href="#ai-plus"></use></svg>New code</button></div>
  </div>

  <section class="a-card">
    <div class="a-card-head"><div><h3>Studio A hourly rates</h3><p>The first paid or held booking for an email, phone number, or account uses the first-time rate. Every booking after that uses the standard rate.</p></div></div>
    <form method="post" action="{{ route('admin.coupons.rates') }}">
      @csrf
      <div class="a-form-grid">
        <label class="a-field"><span>Standard rate (USD / hour)</span><input class="a-input" type="number" min="1" step="0.01" name="standard" value="{{ old('standard', $standard) }}" required></label>
        <label class="a-field"><span>First-time rate (USD / hour)</span><input class="a-input" type="number" min="1" step="0.01" name="first_time" value="{{ old('first_time', $firstTime) }}" required></label>
      </div>
      <x-admin.reason />
      <div class="a-actions" style="margin-top:12px"><button class="a-btn primary" type="submit">Save rates</button></div>
    </form>
  </section>

  <section class="a-card">
    <div class="a-card-head"><div><h3>Codes</h3><p>Turn a code off to stop it immediately. Family codes only work for the email you enter.</p></div></div>
    <div class="a-table-wrap"><table class="a-table">
      <thead><tr><th>Code</th><th>Type</th><th>Off</th><th>Uses</th><th>Expires</th><th>Services</th><th>Customer</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse($coupons as $coupon)
        <tr>
          <td><strong>{{ $coupon->code }}</strong><small>{{ $coupon->name }}</small></td>
          <td>{{ $coupon->kind === 'family' ? 'Family' : 'Coupon' }}</td>
          <td class="num">{{ $coupon->percent }}%</td>
          <td class="num">
            {{ $coupon->used_count }} used / {{ $coupon->max_uses }}
            @if($coupon->reserved_count)
              <small>{{ $coupon->reserved_count }} held at checkout</small>
            @endif
            @if($coupon->max_uses === 1)
              <small>One-time code</small>
            @elseif($coupon->once_per_customer)
              <small>One use per customer</small>
            @endif
          </td>
          <td class="nowrap">{{ $coupon->expires_at?->format('m/d/Y') ?? 'No expiration' }}</td>
          <td>{{ collect($coupon->services)->map(fn ($service) => $services[$service] ?? $service)->join(', ') }}</td>
          <td>{{ $coupon->assigned_email ?: 'Anyone' }}@if($coupon->assignedUser)<small>Account: {{ $coupon->assignedUser->name }}</small>@endif</td>
          <td><span class="a-badge {{ $coupon->is_active ? 'success' : 'neutral' }}">{{ $coupon->is_active ? 'On' : 'Off' }}</span></td>
          <td class="actions"><button class="a-btn ghost sm" type="button" data-modal-open="coupon-{{ $coupon->id }}">Edit</button></td>
        </tr>
      @empty
        <tr><td colspan="9"><div class="a-table-empty"><strong>No codes yet</strong>Create a percent-off code for Studio A, SoundTunnel, or DJ School.</div></td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>

  <section class="a-card">
    <div class="a-card-head"><div><h3>Usage report</h3><p>The latest 100 times a code was reserved, used, or released.</p></div></div>
    <div class="a-table-wrap"><table class="a-table">
      <thead><tr><th>When</th><th>Code</th><th>Customer</th><th>Service</th><th>Off</th><th>Discount</th><th>Status</th><th>Booking</th></tr></thead>
      <tbody>
      @forelse($redemptions as $redemption)
        <tr>
          <td class="nowrap">{{ $redemption->created_at->format('m/d/Y H:i') }}</td>
          <td><strong>{{ $redemption->coupon?->code }}</strong></td>
          <td>{{ $redemption->email }}@if($redemption->phone_digits)<small>{{ $redemption->phone_digits }}</small>@endif</td>
          <td>{{ $services[$redemption->service] ?? $redemption->service }}</td>
          <td class="num">{{ $redemption->percent }}%</td>
          <td class="num">{{ a_money($redemption->discount_cents) }}</td>
          <td><span class="a-badge {{ $redemption->status === 'used' ? 'success' : ($redemption->status === 'reserved' ? 'warn' : 'neutral') }}">{{ ucfirst($redemption->status) }}</span></td>
          <td>{{ $redemption->booking?->reference ?? 'Inquiry' }}</td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="a-table-empty"><strong>No uses yet</strong>Redemptions appear here after a customer applies a code.</div></td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div></section>

@foreach($coupons->concat([null]) as $coupon)
@php($modalId = $coupon ? 'coupon-'.$coupon->id : 'create-coupon')
<x-admin.modal :id="$modalId" :title="$coupon ? 'Edit '.$coupon->code : 'New code'" kicker="Coupon or family discount" subtitle="Percent off is 10, 15, 20, or 30. A family code only works for the email you save.">
  <form method="post" action="{{ $coupon ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
    @csrf @if($coupon)@method('PATCH')@endif
    <input type="hidden" name="_modal" value="{{ $modalId }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Name</span><input class="a-input" name="name" value="{{ old('name', $coupon?->name) }}" required></label>
        <label class="a-field"><span>Code</span><input class="a-input" name="code" value="{{ old('code', $coupon?->code) }}" required placeholder="FAMILY20"></label>
        <label class="a-field"><span>Type</span><select class="a-select" name="kind"><option value="coupon" @selected(old('kind', $coupon?->kind ?? 'coupon') === 'coupon')>Coupon</option><option value="family" @selected(old('kind', $coupon?->kind) === 'family')>Family discount</option></select></label>
        <label class="a-field"><span>Percent off</span><select class="a-select" name="percent">@foreach($percents as $percent)<option value="{{ $percent }}" @selected((int) old('percent', $coupon?->percent ?? 10) === $percent)>{{ $percent }}%</option>@endforeach</select></label>
        <label class="a-field"><span>Expiration</span><input class="a-input" type="date" name="expires_at" value="{{ old('expires_at', $coupon?->expires_at?->format('Y-m-d')) }}"></label>
        <label class="a-field"><span>Number of uses</span><input class="a-input" type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon?->max_uses ?? 1) }}" required><small>1 is a single-use code.</small></label>
        <label class="a-field span-2"><span>Limit to this email</span><input class="a-input" type="email" name="assigned_email" value="{{ old('assigned_email', $coupon?->assigned_email) }}" placeholder="Required for a family discount"><small>Leave blank on a regular coupon to let any customer use it.</small></label>
        <div class="span-2"><span class="a-field"><span>Services</span></span>
          @foreach($services as $value => $label)
            <label class="a-switch" style="margin-top:8px"><input type="checkbox" name="services[]" value="{{ $value }}" @checked(in_array($value, old('services', $coupon?->services ?? ['studio_a']), true))><i></i><span>{{ $label }}</span></label>
          @endforeach
        </div>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="once_per_customer" value="1" @checked(old('once_per_customer', $coupon?->once_per_customer))><i></i><span><strong>One use per customer</strong> — matched by email, phone, or account</span></label></div>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon ? $coupon->is_active : true))><i></i><span><strong>Code is on</strong></span></label></div>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">{{ $coupon ? 'Save code' : 'Create code' }}</button></div>
  </form>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
