<?php page_start('Spaces & pricing — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Settings</p>
      <h2>Spaces & pricing</h2>
      <p>Each bookable space and its duration-based price list. Inactive prices are hidden from the booking flow immediately.</p>
    </div>
    <div class="a-page-actions"><button class="a-btn primary" type="button" data-modal-open="create-price"><svg><use href="#ai-plus"></use></svg>Add price</button></div>
  </div>
  @include('admin.settings._nav')

  @foreach($resources as $resource)
    <section class="a-card">
      <div class="a-card-head">
        <div style="display:flex;align-items:center;gap:14px">
          <span class="a-stat-icon" style="margin:0"><svg class="a-icon"><use href="#ai-{{ $resource->type === 'venue' ? 'ticket' : 'building' }}"></use></svg></span>
          <div><h3>{{ $resource->name }}</h3><p>{{ a_label($resource->type) }} · {{ $resource->timezone }}{{ $resource->capacity ? ' · capacity '.number_format($resource->capacity) : '' }}{{ $resource->calendar_id ? ' · calendar linked' : ' · no calendar mapped' }}</p></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px"><span class="a-badge {{ $resource->is_active ? 'success' : 'danger' }}">{{ $resource->is_active ? 'Bookable' : 'Offline' }}</span><button class="a-btn ghost sm" type="button" data-modal-open="resource-{{ $resource->id }}"><svg><use href="#ai-pencil"></use></svg>Edit space</button></div>
      </div>
      <div class="a-table-wrap"><table class="a-table">
        <thead><tr><th>Price name</th><th>Service</th><th class="num">Duration</th><th class="num">Price</th><th class="num">Per hour</th><th>Effective</th><th>Status</th><th class="actions">Actions</th></tr></thead>
        <tbody>
        @forelse($resource->pricingRules as $rule)
          <tr>
            <td><strong>{{ $rule->name }}</strong><small>ID #{{ $rule->id }}</small></td>
            <td>{{ $serviceTypes[$rule->service_type] ?? a_label($rule->service_type) }}</td>
            <td class="num">{{ $rule->duration_minutes ? rtrim(rtrim(number_format($rule->duration_minutes / 60, 1), '0'), '.').' h' : '—' }}</td>
            <td class="num"><strong>{{ a_money($rule->price_cents) }}</strong></td>
            <td class="num">{{ $rule->duration_minutes ? a_money((int) round($rule->price_cents / ($rule->duration_minutes / 60))) : '—' }}</td>
            <td class="nowrap">{{ $rule->effective_from?->format('M j, Y') ?? 'Now' }} → {{ $rule->effective_until?->format('M j, Y') ?? 'Open-ended' }}</td>
            <td><span class="a-badge {{ $rule->is_active ? 'success' : 'neutral' }}">{{ $rule->is_active ? 'Active' : 'Hidden' }}</span></td>
            <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="price-{{ $rule->id }}" aria-label="Edit {{ $rule->name }}" title="Edit price"><svg><use href="#ai-pencil"></use></svg></button></div></td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="a-table-empty"><strong>No prices yet</strong>Add a price to make {{ $resource->name }} bookable.</div></td></tr>
        @endforelse
        </tbody>
      </table></div>
    </section>
  @endforeach
</div></section>

@foreach($resources as $resource)
<x-admin.modal id="resource-{{ $resource->id }}" title="Edit space" :kicker="$resource->name" subtitle="Core details for this bookable space. Taking a space offline hides it from customers.">
  <form method="post" action="{{ route('admin.resources.update', $resource) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="resource-{{ $resource->id }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Name</span><input class="a-input" name="name" value="{{ old('name', $resource->name) }}" required></label>
        <label class="a-field"><span>Type</span><select class="a-select" name="type">@foreach(['studio' => 'Studio', 'venue' => 'Venue', 'classroom' => 'Classroom', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $resource->type) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field"><span>Timezone</span><input class="a-input" name="timezone" value="{{ old('timezone', $resource->timezone) }}" list="timezones" required><datalist id="timezones">@foreach(['America/Phoenix', 'America/Los_Angeles', 'America/Denver', 'America/Chicago', 'America/New_York'] as $tz)<option value="{{ $tz }}">@endforeach</datalist></label>
        <label class="a-field"><span>Capacity</span><input class="a-input" type="number" min="0" name="capacity" value="{{ old('capacity', $resource->capacity) }}"></label>
        <label class="a-field span-2"><span>Microsoft calendar ID</span><input class="a-input" name="calendar_id" value="{{ old('calendar_id', $resource->calendar_id) }}" placeholder="Mailbox or calendar identifier"><small>Used to sync confirmed bookings into Microsoft 365.</small></label>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource->is_active))><i></i><span><strong>Space is bookable</strong></span></label></div>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save space</button></div>
  </form>
</x-admin.modal>
@endforeach

@foreach($resources->flatMap->pricingRules->concat([null]) as $rule)
@php($modalId = $rule ? 'price-'.$rule->id : 'create-price')
<x-admin.modal :id="$modalId" :title="$rule ? 'Edit price' : 'Add price'" :kicker="$rule?->resource?->name ?? 'New price rule'" subtitle="Prices are stored in cents and shown to customers on the booking page.">
  <form method="post" action="{{ $rule ? route('admin.pricing.update', $rule) : route('admin.pricing.store') }}">
    @csrf @if($rule)@method('PATCH')@endif
    <input type="hidden" name="_modal" value="{{ $modalId }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Space</span><select class="a-select" name="resource_id" required>@foreach($resources as $resource)<option value="{{ $resource->id }}" @selected((string) old('resource_id', $rule?->resource_id) === (string) $resource->id)>{{ $resource->name }}</option>@endforeach</select></label>
        <label class="a-field"><span>Service type</span><select class="a-select" name="service_type">@foreach($serviceTypes as $value => $label)<option value="{{ $value }}" @selected(old('service_type', $rule?->service_type ?? 'studio_booking') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field span-2"><span>Price name</span><input class="a-input" name="name" value="{{ old('name', $rule?->name) }}" placeholder="e.g. 3 Hour Studio A Session" required></label>
        <label class="a-field"><span>Duration (minutes)</span><input class="a-input" type="number" min="15" step="15" name="duration_minutes" value="{{ old('duration_minutes', $rule?->duration_minutes) }}" placeholder="120"></label>
        <label class="a-field"><span>Price (USD)</span><input class="a-input" type="number" min="0" step="0.01" name="price" value="{{ old('price', $rule ? number_format($rule->price_cents / 100, 2, '.', '') : '') }}" required></label>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule ? $rule->is_active : true))><i></i><span><strong>Price is active</strong> — selectable on the booking page</span></label></div>
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">{{ $rule ? 'Save price' : 'Add price' }}</button></div>
  </form>
</x-admin.modal>
@endforeach
<?php page_end(); ?>
