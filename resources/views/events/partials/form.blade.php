<?php
$locked = $event && !in_array($event->status, ['draft', 'changes_requested', 'rejected'], true);
$ticketRows = old('tickets');
if (!$ticketRows) {
    $ticketRows = $event?->ticketTypes?->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => $t->price_cents / 100, 'quantity' => $t->quantity, 'per_order_limit' => $t->per_order_limit])->values()->all() ?: [];
}
while (count($ticketRows) < 1) {
    $ticketRows[] = ['id' => null, 'name' => '', 'price' => '', 'quantity' => '', 'per_order_limit' => 10];
}
$minStart = now('America/Phoenix')->addHour()->startOfHour()->format('Y-m-d\TH:i');
$fmt = fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : $value;
$location = old('location_type', $event?->location_type ?? 'soundtunnel');
?>
@if($errors->any())
  <div class="acct-note is-bad" style="display:block">
    <strong>Please fix the following before saving:</strong>
    <ul style="margin:.4rem 0 0 1.1rem;padding:0">
      @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="post" action="{{ $action }}" class="acct-event-form" data-event-form>
  @csrf @if($method !== 'POST')@method($method)@endif
  <input type="hidden" name="visibility" value="{{ old('visibility', $event?->visibility ?? 'public') }}">

  <div class="acct-panel">
    <h2>Event details</h2>
    <div class="acct-form">
      <label class="acct-form-full">Event title
        <input class="input" name="title" value="{{ old('title', $event?->title) }}" placeholder="e.g. Desert Pulse Artist Showcase" required @disabled($locked)>
        @error('title')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
      <label>Category
        <select class="input" name="category" @disabled($locked)>
          @foreach(['dj_night' => 'DJ night', 'live_music' => 'Live music', 'showcase' => 'Showcase', 'workshop' => 'Workshop', 'open_mic' => 'Open mic / open decks', 'private' => 'Private event', 'community' => 'Community night', 'other' => 'Other'] as $value => $label)
            <option value="{{ $value }}" @selected(old('category', $event?->category) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Location
        <select class="input" name="location_type" data-location @disabled($locked)>
          <option value="soundtunnel" @selected($location === 'soundtunnel')>SoundTunnel at MusiciansWorld</option>
          <option value="external" @selected($location === 'external')>Another venue</option>
          <option value="virtual" @selected($location === 'virtual')>Online / virtual</option>
        </select>
      </label>
      <label data-location-field="external" @if($location !== 'external') hidden @endif>Venue name
        <input class="input" name="venue_name" value="{{ old('venue_name', $event?->venue_name) }}" placeholder="Venue name" @disabled($locked)>
      </label>
      <label data-location-field="external" @if($location !== 'external') hidden @endif>Venue address
        <input class="input" name="venue_address" value="{{ old('venue_address', $event?->venue_address) }}" placeholder="Street, city, state" @disabled($locked)>
      </label>
      <label class="acct-form-full" data-location-field="virtual" @if($location !== 'virtual') hidden @endif>Virtual access details
        <input class="input" name="virtual_access" value="{{ old('virtual_access', $event?->virtual_access) }}" placeholder="Stream link or instructions sent to ticket holders" @disabled($locked)>
      </label>
      <label class="acct-form-full">Description
        <textarea class="input" name="description" rows="5" placeholder="Tell attendees what to expect — lineup, format, age policy." required @disabled($locked)>{{ old('description', $event?->description) }}</textarea>
        @error('description')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
    </div>
  </div>

  <div class="acct-panel">
    <div class="acct-panel-head"><h2>Schedule</h2><span class="acct-meta">Times are in Arizona (MST). Click a field to open the calendar.</span></div>
    <div class="acct-form acct-form-3">
      <label>Starts
        <input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $fmt($event?->starts_at?->timezone('America/Phoenix'))) }}" min="{{ $event ? '' : $minStart }}" step="900" data-start required @disabled($locked)>
        @error('starts_at')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
      <label>Ends
        <input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $fmt($event?->ends_at?->timezone('America/Phoenix'))) }}" step="900" data-end required @disabled($locked)>
        @error('ends_at')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
      <label>Doors open <small class="acct-hint">optional</small>
        <input class="input" type="datetime-local" name="doors_at" value="{{ old('doors_at', $fmt($event?->doors_at?->timezone('America/Phoenix'))) }}" step="900" data-doors @disabled($locked)>
        @error('doors_at')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
      <label>Capacity
        <input class="input" type="number" name="capacity" min="1" max="100000" value="{{ old('capacity', $event?->capacity ?? 100) }}" required @disabled($locked)>
        @error('capacity')<small class="acct-field-error">{{ $message }}</small>@enderror
      </label>
      <label>Setup minutes <small class="acct-hint">before doors</small>
        <input class="input" type="number" name="setup_minutes" min="0" max="1440" step="15" value="{{ old('setup_minutes', $event?->setup_minutes ?? 0) }}" @disabled($locked)>
      </label>
      <label>Teardown minutes <small class="acct-hint">after end</small>
        <input class="input" type="number" name="teardown_minutes" min="0" max="1440" step="15" value="{{ old('teardown_minutes', $event?->teardown_minutes ?? 0) }}" @disabled($locked)>
      </label>
    </div>
  </div>

  <div class="acct-panel">
    <div class="acct-panel-head">
      <div><h2>Ticket types</h2><p class="acct-lead" style="margin-top:.3rem">At least one ticket type is required to submit. Use price 0 for free RSVP.</p></div>
      @unless($locked)<button class="btn btn-ghost" type="button" data-add-ticket>Add ticket type</button>@endunless
    </div>
    <div class="acct-ticket-rows" data-ticket-rows>
      @foreach($ticketRows as $i => $row)
        <div class="acct-ticket-row" data-ticket-row>
          <input type="hidden" name="tickets[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
          <label>Name<input class="input" name="tickets[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="General admission" @disabled($locked)></label>
          <label>Price (USD)<input class="input" type="number" min="0" step="0.01" name="tickets[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" placeholder="0.00" @disabled($locked)></label>
          <label>Quantity<input class="input" type="number" min="1" name="tickets[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" placeholder="100" @disabled($locked)></label>
          <label>Per order<input class="input" type="number" min="1" max="50" name="tickets[{{ $i }}][per_order_limit]" value="{{ $row['per_order_limit'] ?? 10 }}" @disabled($locked)></label>
          @unless($locked)<button class="acct-text-btn" type="button" data-remove-ticket aria-label="Remove ticket type" @if(!empty($row['id'])) hidden @endif>Remove</button>@endunless
        </div>
      @endforeach
    </div>
    <template data-ticket-template>
      <div class="acct-ticket-row" data-ticket-row>
        <input type="hidden" name="tickets[__i__][id]" value="">
        <label>Name<input class="input" name="tickets[__i__][name]" placeholder="VIP / Early bird"></label>
        <label>Price (USD)<input class="input" type="number" min="0" step="0.01" name="tickets[__i__][price]" placeholder="0.00"></label>
        <label>Quantity<input class="input" type="number" min="1" name="tickets[__i__][quantity]" placeholder="50"></label>
        <label>Per order<input class="input" type="number" min="1" max="50" name="tickets[__i__][per_order_limit]" value="10"></label>
        <button class="acct-text-btn" type="button" data-remove-ticket aria-label="Remove ticket type">Remove</button>
      </div>
    </template>
  </div>

  <div class="acct-panel">
    <div class="acct-panel-head"><h2>SoundTunnel services</h2><span class="acct-meta">Optional add-ons billed against your event proceeds at closeout.</span></div>
    <div class="acct-services">
      @foreach($services as $service)
        <label class="acct-service">
          <span><strong>{{ $service->name }}</strong><small>{{ $service->price_cents ? a_money($service->price_cents).' per '.($service->unit ?: 'event') : 'Quoted by MWCN' }}</small></span>
          <input class="input" type="number" min="0" max="20" name="services[{{ $service->id }}]" value="{{ old('services.'.$service->id, $event?->services?->firstWhere('id', $service->id)?->pivot?->quantity ?? 0) }}" @disabled($locked)>
        </label>
      @endforeach
    </div>
  </div>

  @unless($locked)
    <div class="acct-actions" style="margin-top:0">
      <div class="acct-pills">
        <button class="btn btn-mark" type="submit">{{ $event ? 'Save changes' : 'Save draft' }}</button>
        <a class="btn btn-ghost" href="{{ route('account.events') }}">Back to My Events</a>
      </div>
      <span class="acct-meta">Drafts are private until you submit them to MWCN for review.</span>
    </div>
  @endunless
</form>

<script>
(function () {
  var form = document.querySelector('[data-event-form]');
  if (!form) return;

  // Native date/time pickers: open on click anywhere in the field.
  form.querySelectorAll('input[type="datetime-local"]').forEach(function (input) {
    input.addEventListener('click', function () {
      if (typeof input.showPicker === 'function' && !input.disabled) {
        try { input.showPicker(); } catch (e) { /* browser requires a user gesture; ignore */ }
      }
    });
  });

  // Keep end/doors sensible relative to the start time.
  var start = form.querySelector('[data-start]'), end = form.querySelector('[data-end]'), doors = form.querySelector('[data-doors]');
  function pad(n) { return String(n).padStart(2, '0'); }
  function toLocal(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  if (start) {
    // No min/max constraints on end/doors: the native "Value must be … or later" messages are
    // confusing, so the server validates ordering and returns plain-language errors instead.
    start.addEventListener('change', function () {
      if (!start.value) return;
      var s = new Date(start.value);
      if (end && (!end.value || new Date(end.value) <= s)) { end.value = toLocal(new Date(s.getTime() + 4 * 3600000)); }
      if (doors && (!doors.value || new Date(doors.value) >= s)) { doors.value = toLocal(new Date(s.getTime() - 3600000)); }
    });
  }

  // Location-specific fields.
  var location = form.querySelector('[data-location]');
  function syncLocation() {
    form.querySelectorAll('[data-location-field]').forEach(function (el) {
      el.hidden = el.getAttribute('data-location-field') !== location.value;
    });
  }
  if (location) { location.addEventListener('change', syncLocation); syncLocation(); }

  // Ticket type rows.
  var rows = form.querySelector('[data-ticket-rows]'), tpl = form.querySelector('[data-ticket-template]'), add = form.querySelector('[data-add-ticket]');
  if (add && rows && tpl) {
    add.addEventListener('click', function () {
      var index = rows.querySelectorAll('[data-ticket-row]').length;
      var html = tpl.innerHTML.replace(/__i__/g, index);
      rows.insertAdjacentHTML('beforeend', html);
      rows.lastElementChild.querySelector('input[name$="[name]"]').focus();
    });
  }
  form.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-remove-ticket]');
    if (!btn) return;
    var row = btn.closest('[data-ticket-row]');
    if (rows.querySelectorAll('[data-ticket-row]').length > 1) { row.remove(); } else { row.querySelectorAll('input:not([type=hidden])').forEach(function (i) { i.value = i.name.endsWith('[per_order_limit]') ? 10 : ''; }); }
  });
})();
</script>
