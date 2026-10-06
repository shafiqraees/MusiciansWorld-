<?php page_start('Availability holds — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Settings</p>
      <h2>Availability holds</h2>
      <p>Blocks that prevent customer bookings during maintenance, private use or administrative holds.</p>
    </div>
    <div class="a-page-actions"><button class="a-btn primary" type="button" data-modal-open="create-hold"><svg><use href="#ai-plus"></use></svg>Block availability</button></div>
  </div>
  @include('admin.settings._nav')

  <div class="a-tabs">
    <a class="a-tab {{ !$showPast ? 'is-active' : '' }}" href="{{ route('admin.settings.availability') }}">Active & upcoming</a>
    <a class="a-tab {{ $showPast ? 'is-active' : '' }}" href="{{ route('admin.settings.availability', ['past' => 1]) }}">Full history</a>
  </div>

  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>Space</th><th>Type</th><th>Starts</th><th>Ends</th><th class="num">Duration</th><th>Reason</th><th>Created</th><th class="actions">Actions</th></tr></thead>
      <tbody>
      @forelse($holds as $hold)
        @php($tz = $hold->resource?->timezone ?? 'America/Phoenix')
        <tr>
          <td><strong>{{ $hold->resource?->name }}</strong></td>
          <td><x-admin.badge :status="$hold->type" :label="$types[$hold->type] ?? a_label($hold->type)" /></td>
          <td class="nowrap">{{ $hold->starts_at->setTimezone($tz)->format('D, M j, Y') }}<small>{{ $hold->starts_at->setTimezone($tz)->format('g:i A') }}</small></td>
          <td class="nowrap">{{ $hold->ends_at->setTimezone($tz)->format('D, M j, Y') }}<small>{{ $hold->ends_at->setTimezone($tz)->format('g:i A') }}</small></td>
          <td class="num">{{ rtrim(rtrim(number_format($hold->starts_at->diffInMinutes($hold->ends_at) / 60, 1), '0'), '.') }} h</td>
          <td><small style="white-space:normal;max-width:360px">{{ $hold->reason }}</small></td>
          <td class="nowrap">{{ $hold->created_at->format('M j, Y') }}</td>
          <td class="actions"><div class="a-row-actions">
            @if($hold->ends_at->isFuture())
              <form method="post" action="{{ route('admin.holds.delete', $hold) }}" data-confirm="Remove this availability hold? Customers will be able to book this window again."> @csrf @method('DELETE')<button class="a-btn danger icon sm" type="submit" aria-label="Remove hold" title="Remove hold"><svg><use href="#ai-trash"></use></svg></button></form>
            @else<span class="a-sub">Ended</span>@endif
          </div></td>
        </tr>
      @empty
        <tr><td colspan="8"><div class="a-table-empty"><strong>No availability holds</strong>Spaces are fully open for booking.</div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $holds->links('admin.partials.pagination') }}
</div></section>

<x-admin.modal id="create-hold" title="Block availability" kicker="Availability hold" subtitle="The selected window becomes unavailable to customers immediately.">
  <form method="post" action="{{ route('admin.holds.create') }}">
    @csrf
    <input type="hidden" name="_modal" value="create-hold">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Space</span><select class="a-select" name="resource_id" required>@foreach($resources as $resource)<option value="{{ $resource->id }}" @selected((string) old('resource_id') === (string) $resource->id)>{{ $resource->name }}</option>@endforeach</select></label>
        <label class="a-field"><span>Hold type</span><select class="a-select" name="type">@foreach($types as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field"><span>Starts</span><input class="a-input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required></label>
        <label class="a-field"><span>Ends</span><input class="a-input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required></label>
      </div>
      <x-admin.reason label="Reason for the hold" placeholder="Shown to the team in the booking desk" />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Create hold</button></div>
  </form>
</x-admin.modal>
<?php page_end(); ?>
