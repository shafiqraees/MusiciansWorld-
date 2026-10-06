<?php page_start('Platform settings — MWCN ERP'); $allSettings = $groups->flatten(); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Settings</p>
      <h2>Platform settings</h2>
      <p>Fees, booking rules and feature switches. Values are typed so the platform reads them safely.</p>
    </div>
    <div class="a-page-actions"><button class="a-btn primary" type="button" data-modal-open="create-setting"><svg><use href="#ai-plus"></use></svg>New setting</button></div>
  </div>
  @include('admin.settings._nav')

  @forelse($groups as $group => $settings)
    <section class="a-card">
      <div class="a-card-head"><div><h3>{{ $groupLabels[$group]['label'] ?? a_label($group) }}</h3><p>{{ $groupLabels[$group]['intro'] ?? 'Custom settings group.' }}</p></div><span class="a-badge neutral plain">{{ $settings->count() }} {{ str('setting')->plural($settings->count()) }}</span></div>
      <div class="a-table-wrap"><table class="a-table">
        <thead><tr><th>Key</th><th>Value</th><th>Type</th><th>Visibility</th><th>Updated</th><th class="actions">Actions</th></tr></thead>
        <tbody>
        @foreach($settings as $setting)
          <tr>
            <td><code class="a-code">{{ $setting->key }}</code></td>
            <td>@if($setting->type === 'boolean')<span class="a-badge {{ filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) ? 'success' : 'neutral' }}">{{ filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled' }}</span>@elseif($setting->type === 'json')<code class="a-code">{{ str($setting->value)->limit(80) }}</code>@else<strong>{{ $setting->value ?? '—' }}</strong>@if(str_ends_with($setting->key, '.bps') && is_numeric($setting->value))<small>= {{ $setting->value / 100 }}%</small>@endif @endif</td>
            <td>{{ $types[$setting->type] ?? $setting->type }}</td>
            <td><span class="a-badge plain {{ $setting->is_public ? 'info' : 'neutral' }}">{{ $setting->is_public ? 'Public' : 'Internal' }}</span></td>
            <td class="nowrap">{{ $setting->updated_at?->format('M j, Y') }}<small>{{ $setting->updated_at?->diffForHumans() }}</small></td>
            <td class="actions"><div class="a-row-actions"><button class="a-btn ghost icon sm" type="button" data-modal-open="setting-{{ $setting->id }}" aria-label="Edit {{ $setting->key }}" title="Edit setting"><svg><use href="#ai-pencil"></use></svg></button></div></td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </section>
  @empty
    <div class="a-card"><div class="a-empty"><strong>No settings defined</strong>Create the first platform setting to get started.</div></div>
  @endforelse
</div></section>

@foreach($allSettings as $setting)
<x-admin.modal id="setting-{{ $setting->id }}" title="Edit setting" :kicker="a_label($setting->group)" :subtitle="$setting->key">
  <form method="post" action="{{ route('admin.settings.update', $setting) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="_modal" value="setting-{{ $setting->id }}">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field span-2"><span>Key</span><input class="a-input" value="{{ $setting->key }}" readonly></label>
        <label class="a-field"><span>Type</span><select class="a-select" name="type">@foreach($types as $value => $label)<option value="{{ $value }}" @selected(old('type', $setting->type) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="a-field"><span>Visibility</span><label class="a-switch" style="min-height:42px"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $setting->is_public))><i></i><span>Expose to public website</span></label></div>
        @if($setting->type === 'boolean')
          <div class="a-field span-2"><span>Value</span><div class="a-check-grid"><label class="a-check"><input type="radio" name="value" value="true" @checked(old('value', $setting->value) === 'true')><span><strong>Enabled</strong></span></label><label class="a-check"><input type="radio" name="value" value="false" @checked(old('value', $setting->value) !== 'true')><span><strong>Disabled</strong></span></label></div></div>
        @elseif($setting->type === 'json')
          <label class="a-field span-2"><span>Value (JSON)</span><textarea class="a-textarea" name="value" style="font-family:ui-monospace,monospace">{{ old('value', $setting->value) }}</textarea></label>
        @else
          <label class="a-field span-2"><span>Value</span><input class="a-input" name="value" value="{{ old('value', $setting->value) }}" @if($setting->type === 'integer') type="number" step="1" @endif>@if(str_ends_with($setting->key, '.bps'))<small>Basis points — 500 = 5%.</small>@elseif(str_contains($setting->key, 'minutes'))<small>Duration in minutes.</small>@endif</label>
        @endif
      </div>
      <x-admin.reason />
    </div>
    <div class="a-modal-foot"><span class="left">Last changed {{ $setting->updated_at?->diffForHumans() }}</span><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Save setting</button></div>
  </form>
</x-admin.modal>
@endforeach

<x-admin.modal id="create-setting" title="New platform setting" kicker="Settings" subtitle="Add a configuration key the application can read with PlatformSetting::valueOf().">
  <form method="post" action="{{ route('admin.settings.store') }}">
    @csrf
    <input type="hidden" name="_modal" value="create-setting">
    <div class="a-modal-body">
      <div class="a-form-grid">
        <label class="a-field"><span>Group</span><input class="a-input" name="group" value="{{ old('group') }}" list="setting-groups" placeholder="fees, booking, stripe…" required pattern="[a-z0-9_\-]+"><datalist id="setting-groups">@foreach($groups->keys() as $group)<option value="{{ $group }}">@endforeach</datalist></label>
        <label class="a-field"><span>Type</span><select class="a-select" name="type">@foreach($types as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="a-field span-2"><span>Key</span><input class="a-input" name="key" value="{{ old('key') }}" placeholder="e.g. booking.max_hours" required pattern="[a-z0-9_.\-]+"><small>Lowercase letters, numbers, dots, dashes and underscores.</small></label>
        <label class="a-field span-2"><span>Value</span><input class="a-input" name="value" value="{{ old('value') }}"><small>For on/off settings use <code class="a-code">true</code> or <code class="a-code">false</code>.</small></label>
        <div class="span-2"><label class="a-switch"><input type="checkbox" name="is_public" value="1" @checked(old('is_public'))><i></i><span>Expose to public website</span></label></div>
      </div>
      <x-admin.reason label="Reason for adding this setting" />
    </div>
    <div class="a-modal-foot"><button class="a-btn ghost" type="button" data-modal-close>Cancel</button><button class="a-btn primary" type="submit">Create setting</button></div>
  </form>
</x-admin.modal>
<?php page_end(); ?>
