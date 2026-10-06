<?php page_start('Settings — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Configuration</p>
      <h2>Settings</h2>
      <p>Platform configuration is organised into focused sections so each area stays easy to review and change. Every change requires a reason and is written to the audit trail.</p>
    </div>
  </div>
  @include('admin.settings._nav')

  <div class="a-hub">
    @foreach($sections as $key => $section)
      @php($route = 'admin.settings.'.$key)
      @if(($key === 'availability' && auth()->user()->hasPermission('bookings.manage')) || ($key !== 'availability' && auth()->user()->hasPermission('settings.manage')))
      <a class="a-hub-card" href="{{ route($route) }}">
        <span class="a-stat-icon"><svg class="a-icon"><use href="#ai-{{ $section['icon'] }}"></use></svg></span>
        <span><h3>{{ $section['label'] }}</h3><p>{{ $section['intro'] }}</p></span>
        <b>{{ $counts[$key] }} {{ ['platform' => 'settings', 'pricing' => 'prices', 'availability' => 'active holds', 'integrations' => 'connected'][$key] }}</b>
      </a>
      @endif
    @endforeach
    <a class="a-hub-card" href="{{ route('admin.memberships') }}">
      <span class="a-stat-icon"><svg class="a-icon"><use href="#ai-badge"></use></svg></span>
      <span><h3>Membership plans & benefits</h3><p>Plan catalogue, pricing, Stripe price IDs and the benefits each tier unlocks.</p></span>
      <b>{{ \App\Models\MembershipPlan::count() }} plans</b>
    </a>
    <a class="a-hub-card" href="{{ route('admin.roles') }}">
      <span class="a-stat-icon"><svg class="a-icon"><use href="#ai-shield"></use></svg></span>
      <span><h3>Roles & permissions</h3><p>Define what each internal role can see and do across the portal.</p></span>
      <b>{{ \App\Models\Role::count() }} roles</b>
    </a>
  </div>

  <section class="a-card" style="margin-top:20px">
    <div class="a-card-head"><div><h3>Environment</h3><p>Read-only values from the server configuration. Change these in <code class="a-code">.env</code>, not in the portal.</p></div></div>
    <div class="a-card-body">
      <div class="a-kv">
        <div><small>Application</small><strong>{{ config('app.name') }}</strong></div>
        <div><small>Environment</small><strong>{{ config('app.env') }}{{ config('app.debug') ? ' · debug on' : '' }}</strong></div>
        <div><small>Public URL</small><strong>{{ config('app.url') }}</strong></div>
        <div><small>Timezone</small><strong>{{ config('services.mwcn.timezone', config('app.timezone')) }}</strong></div>
        <div><small>Laravel</small><strong>{{ app()->version() }}</strong></div>
        <div><small>PHP</small><strong>{{ PHP_VERSION }}</strong></div>
      </div>
    </div>
  </section>
</div></section>
<?php page_end(); ?>
