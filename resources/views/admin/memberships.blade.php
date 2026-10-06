<?php page_start('Memberships & benefits — MWCN ERP'); $dt = fn ($value) => $value?->format('Y-m-d\TH:i'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Commerce</p>
      <h2>Memberships & benefits</h2>
      <p>Manage the plan catalogue, the benefits each plan unlocks, and every customer subscription in one place.</p>
    </div>
    <div class="a-page-actions">
      <button class="a-btn ghost" type="button" data-modal-open="assign-membership"><svg><use href="#ai-user"></use></svg>Assign membership</button>
      <button class="a-btn primary" type="button" data-modal-open="create-plan"><svg><use href="#ai-plus"></use></svg>New plan</button>
    </div>
  </div>

  <div class="a-stats">
    <article class="a-stat"><small>Active members</small><strong>{{ number_format($stats['active']) }}</strong><span>Active, trialing or in grace</span></article>
    <article class="a-stat"><small>Paid memberships</small><strong>{{ number_format($stats['paid']) }}</strong><span>On a priced plan</span></article>
    <article class="a-stat"><small>Needs attention</small><strong>{{ number_format($stats['grace']) }}</strong><span>Grace period or past due</span></article>
    <article class="a-stat"><small>Monthly recurring</small><strong>{{ a_money($stats['mrr_cents']) }}</strong><span>Active monthly plans</span></article>
  </div>

  <div class="a-tabs" data-tabs="tab">
    <button class="a-tab {{ $tab === 'members' ? '' : 'is-active' }}" type="button" data-tab="plans">Plans & benefits <b>{{ $plans->count() }}</b></button>
    <button class="a-tab {{ $tab === 'members' ? 'is-active' : '' }}" type="button" data-tab="members">Member subscriptions <b>{{ $memberships->total() }}</b></button>
  </div>

  {{-- Plans --}}
  <div data-tab-panel="plans" data-tab-group="tab" class="a-stack">
    <div class="a-table-wrap">
      <table class="a-table">
        <thead><tr><th>Plan</th><th>Price</th><th>Stripe price</th><th>Benefits</th><th class="num">Members</th><th>Status</th><th class="num">Order</th><th class="actions">Actions</th></tr></thead>
        <tbody>
        @forelse($plans as $plan)
          <tr>
            <td><strong>{{ $plan->name }}</strong><small><code class="a-code">{{ $plan->slug }}</code>{{ $plan->description ? ' · '.str($plan->description)->limit(70) : '' }}</small></td>
            <td class="nowrap"><strong>{{ $plan->price_cents > 0 ? a_money($plan->price_cents) : 'Free' }}</strong><small>{{ $intervals[$plan->billing_interval] ?? a_label($plan->billing_interval) }}</small></td>
            <td>@if($plan->stripe_price_id)<code class="a-code">{{ $plan->stripe_price_id }}</code>@else<span class="a-sub">Not linked</span>@endif</td>
            <td><div class="a-badges">@forelse($plan->benefits as $benefit)<span class="a-pill" title="{{ $benefit->key }}">{{ $benefit->name }}: <strong>{{ $benefit->pivot->value }}</strong></span>@empty<span class="a-sub">No benefits configured</span>@endforelse</div></td>
            <td class="num"><strong>{{ $plan->active_memberships_count }}</strong><small>{{ $plan->memberships_count }} total</small></td>
            <td><span class="a-badge {{ $plan->is_active ? 'success' : 'neutral' }}">{{ $plan->is_active ? 'Active' : 'Hidden' }}</span></td>
            <td class="num">{{ $plan->sort_order }}</td>
            <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="plan-{{ $plan->id }}" aria-label="Edit {{ $plan->name }}" title="Edit plan"><svg><use href="#ai-pencil"></use></svg></button></div></td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="a-table-empty"><strong>No plans yet</strong>Create the first membership plan to get started.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <section class="a-card">
      <div class="a-card-head"><div><h3>Benefit catalogue</h3><p>Benefits are keys the platform understands. Set a value per plan from the plan editor.</p></div></div>
      <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Benefit</th><th>Key</th><th>Type</th><th>Description</th>@foreach($plans as $plan)<th class="num">{{ $plan->name }}</th>@endforeach</tr></thead><tbody>
        @foreach($benefits as $benefit)
          <tr><td><strong>{{ $benefit->name }}</strong></td><td><code class="a-code">{{ $benefit->key }}</code></td><td>{{ a_label($benefit->type) }}</td><td><small style="white-space:normal">{{ $benefit->description ?: '—' }}</small></td>@foreach($plans as $plan)<td class="num">{{ $plan->benefits->firstWhere('id', $benefit->id)?->pivot?->value ?? '—' }}</td>@endforeach</tr>
        @endforeach
      </tbody></table></div>
    </section>
  </div>

  {{-- Member subscriptions --}}
  <div data-tab-panel="members" data-tab-group="tab" hidden>
    <form method="get" class="a-toolbar">
      <input type="hidden" name="tab" value="members">
      <label class="a-field grow"><span>Search member</span><input class="a-input" name="search" value="{{ request('search') }}" placeholder="Name or email"></label>
      <label class="a-field"><span>Plan</span><select class="a-select" name="plan"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string) request('plan') === (string) $plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
      <label class="a-field"><span>Status</span><select class="a-select" name="status"><option value="">Any status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
      <div class="a-actions"><button class="a-btn primary" type="submit"><svg><use href="#ai-search"></use></svg>Filter</button><a class="a-btn ghost" href="{{ route('admin.memberships', ['tab' => 'members']) }}">Reset</a></div>
    </form>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead><tr><th>Member</th><th>Plan</th><th>Status</th><th>Current period</th><th>Grace / cancelled</th><th>Stripe</th><th>Since</th><th class="actions">Actions</th></tr></thead>
        <tbody>
        @forelse($memberships as $membership)
          <tr>
            <td><div class="a-user-cell"><span class="a-avatar neutral sm">{{ $membership->user?->initials() ?? '?' }}</span><span><strong>@if($membership->user)<a class="a-link" style="color:inherit" href="{{ route('admin.users.show', $membership->user) }}">{{ $membership->user->name }}</a>@else Deleted user @endif</strong><small>{{ $membership->user?->email }}</small></span></div></td>
            <td><strong>{{ $membership->plan?->name }}</strong><small>{{ $membership->plan?->price_cents > 0 ? a_money($membership->plan->price_cents).' / '.a_label($membership->plan->billing_interval) : 'Free' }}</small></td>
            <td><x-admin.badge :status="$membership->status" :label="$statuses[$membership->status] ?? a_label($membership->status)" /></td>
            <td class="nowrap">{{ $membership->current_period_start?->format('M j, Y') ?? '—' }} → {{ $membership->current_period_end?->format('M j, Y') ?? '—' }}</td>
            <td class="nowrap">@if($membership->grace_ends_at)<span class="a-badge warn plain">Grace until {{ $membership->grace_ends_at->format('M j') }}</span>@endif @if($membership->cancelled_at)<small>Cancelled {{ $membership->cancelled_at->format('M j, Y') }}</small>@endif @if(!$membership->grace_ends_at && !$membership->cancelled_at)<span class="a-sub">—</span>@endif</td>
            <td><code class="a-code">{{ $membership->stripe_customer_id ?: '—' }}</code><small>{{ $membership->stripe_subscription_id ?: 'No subscription' }}</small></td>
            <td class="nowrap">{{ $membership->created_at->format('M j, Y') }}</td>
            <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="membership-{{ $membership->id }}" aria-label="Edit membership" title="Edit membership"><svg><use href="#ai-pencil"></use></svg></button></div></td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="a-table-empty"><strong>No memberships match</strong>Adjust the filters or assign a membership to a customer.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $memberships->appends(['tab' => 'members'])->links('admin.partials.pagination') }}
  </div>
</div></section>

{{-- Plan modals --}}
@foreach($plans->concat([null]) as $plan)
@php($modalId = $plan ? 'plan-'.$plan->id : 'create-plan')
<x-admin.modal :id="$modalId" :title="$plan ? 'Edit plan' : 'Create plan'" :kicker="$plan?->name ?? 'New membership plan'" subtitle="Pricing, Stripe linkage, visibility and the benefits members receive." size="lg">
  <form method="post" action="{{ $plan ? route('admin.plans.update', $plan) : route('admin.plans.store') }}">
    @csrf @if($plan)@method('PATCH')@endif
    <input type="hidden" name="_modal" value="{{ $modalId }}">
    <div class="a-modal-body">
      @if($plan)<div class="a-modal-summary"><div><small>Active members</small><strong>{{ $plan->active_memberships_count }}</strong></div><div><small>All-time members</small><strong>{{ $plan->memberships_count }}</strong></div><div><small>Last updated</small><strong>{{ $plan->updated_at?->format('M j, Y') }}</strong></div></div>@endif
      <div class="a-form-grid">
        <label class="a-field"><span>Plan name</span><input class="a-input" name="name" value="{{ old('name', $plan?->name) }}" required></label>
        <label class="a-field"><span>Slug</span><input class="a-input" name="slug" value="{{ old('slug', $plan?->slug) }}" placeholder="auto-generated from name"><small>Used in code and URLs. Changing it can affect integrations.</small></label>
        <label class="a-field"><span>Price (USD)</span><input class="a-input" type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan ? number_format($plan->price_cents / 100, 2, '.', '') : '0.00') }}" required></label>
        <label class="a-field"><span>Billing interval</span><select class="a-select" name="billing_interval">@foreach($intervals as $value => $label)<option value="{{ $value }}" @selected(old('billing_interval', $plan?->billing_interval ?? 'month') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field"><span>Stripe price ID</span><input class="a-input" name="stripe_price_id" value="{{ old('stripe_price_id', $plan?->stripe_price_id) }}" placeholder="price_…"></label>
        <label class="a-field"><span>Display order</span><input class="a-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan?->sort_order) }}"></label>
        <label class="a-field span-2"><span>Description</span><textarea class="a-textarea" name="description" style="min-height:72px">{{ old('description', $plan?->description) }}</textarea></label>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan ? $plan->is_active : true))><i></i><span><strong>Plan is active</strong> — visible to customers and available for checkout</span></label></div>
      </div>
      <div class="a-form-section">
        <h4>Benefits</h4>
        <div class="a-form-grid">
          @foreach($benefits as $benefit)
            <label class="a-field"><span>{{ $benefit->name }}</span><input class="a-input" name="benefits[{{ $benefit->id }}]" value="{{ old('benefits.'.$benefit->id, $plan?->benefits->firstWhere('id', $benefit->id)?->pivot?->value) }}" placeholder="Leave blank to exclude"><small><code class="a-code">{{ $benefit->key }}</code> · {{ a_label($benefit->type) }}</small></label>
          @endforeach
        </div>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">{{ $plan ? 'Save plan' : 'Create plan' }}</button></div>
  </form>
</x-admin.modal>
@endforeach

{{-- Membership modals --}}
@foreach($memberships->getCollection()->concat([null]) as $membership)
@php($modalId = $membership ? 'membership-'.$membership->id : 'assign-membership')
<x-admin.modal :id="$modalId" :title="$membership ? 'Edit membership' : 'Assign membership'" :kicker="$membership?->user?->name ?? 'Customer subscription'" subtitle="Plan, status, billing period and Stripe identifiers for this subscription." size="lg">
  <form method="post" action="{{ $membership ? route('admin.memberships.update', $membership) : route('admin.memberships.store') }}">
    @csrf @if($membership)@method('PATCH')@endif
    <input type="hidden" name="_modal" value="{{ $modalId }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        @if($membership)
          <div class="a-field span-2"><span>Member</span><div class="a-user-cell" style="padding:10px 12px;border:1px solid var(--a-border);border-radius:8px;background:var(--a-surface-2)"><span class="a-avatar neutral sm">{{ $membership->user?->initials() ?? '?' }}</span><span><strong>{{ $membership->user?->name ?? 'Deleted user' }}</strong><small>{{ $membership->user?->email }}</small></span></div></div>
        @else
          <label class="a-field span-2"><span>Customer</span><select class="a-select" name="user_id" required><option value="">Select a customer…</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('user_id') === (string) $customer->id)>{{ $customer->name }} — {{ $customer->email }}</option>@endforeach</select></label>
        @endif
        <label class="a-field"><span>Plan</span><select class="a-select" name="membership_plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string) old('membership_plan_id', $membership?->membership_plan_id) === (string) $plan->id)>{{ $plan->name }} ({{ $plan->price_cents > 0 ? a_money($plan->price_cents) : 'Free' }})</option>@endforeach</select></label>
        <label class="a-field"><span>Status</span><select class="a-select" name="status">@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $membership?->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field"><span>Period start</span><input class="a-input" type="datetime-local" name="current_period_start" value="{{ old('current_period_start', $dt($membership?->current_period_start)) }}"></label>
        <label class="a-field"><span>Period end</span><input class="a-input" type="datetime-local" name="current_period_end" value="{{ old('current_period_end', $dt($membership?->current_period_end)) }}"></label>
        <label class="a-field"><span>Grace ends</span><input class="a-input" type="datetime-local" name="grace_ends_at" value="{{ old('grace_ends_at', $dt($membership?->grace_ends_at)) }}"></label>
        <label class="a-field"><span>Cancelled at</span><input class="a-input" type="datetime-local" name="cancelled_at" value="{{ old('cancelled_at', $dt($membership?->cancelled_at)) }}"><small>Set automatically when status is Cancelled.</small></label>
        <label class="a-field"><span>Stripe customer ID</span><input class="a-input" name="stripe_customer_id" value="{{ old('stripe_customer_id', $membership?->stripe_customer_id) }}" placeholder="cus_…"></label>
        <label class="a-field"><span>Stripe subscription ID</span><input class="a-input" name="stripe_subscription_id" value="{{ old('stripe_subscription_id', $membership?->stripe_subscription_id) }}" placeholder="sub_…"></label>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot">@if($membership)<span class="left">Created {{ $membership->created_at->format('M j, Y') }} · updated {{ $membership->updated_at->diffForHumans() }}</span>@endif<button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">{{ $membership ? 'Save membership' : 'Assign membership' }}</button></div>
  </form>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
