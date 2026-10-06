<?php page_start('Integrations — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Settings</p>
      <h2>Integrations</h2>
      <p>Connection health for the external services the platform depends on. Credentials live in the server environment and are never shown here.</p>
    </div>
  </div>
  @include('admin.settings._nav')

  <div class="a-grid cols-2">
    @foreach($integrations as $integration)
      <section class="a-card" style="margin:0">
        <div class="a-card-head"><div><h3>{{ $integration['name'] }}</h3><p>{{ $integration['mode'] }}</p></div><span class="a-badge {{ $integration['connected'] ? 'success' : 'warn' }}">{{ $integration['connected'] ? 'Connected' : 'Attention' }}</span></div>
        <div class="a-card-body">
          <p style="margin:0 0 12px">{{ $integration['detail'] }}</p>
          <div class="a-badges">@foreach($integration['env'] as $key)<span class="a-pill"><code class="a-code" style="background:transparent;padding:0">{{ $key }}</code></span>@endforeach</div>
        </div>
      </section>
    @endforeach
  </div>

  <section class="a-card" style="margin-top:20px">
    <div class="a-card-head"><div><h3>Recent webhook events</h3><p>Latest events received from payment providers.</p></div>@if($failedJobs)<span class="a-badge danger">{{ $failedJobs }} failed automation {{ str('job')->plural($failedJobs) }}</span>@endif</div>
    <div class="a-table-wrap"><table class="a-table">
      <thead><tr><th>Provider</th><th>Type</th><th>External ID</th><th>Status</th><th class="num">Attempts</th><th>Received</th></tr></thead>
      <tbody>
      @forelse($recentWebhooks as $webhook)
        <tr><td>{{ ucfirst($webhook->provider) }}</td><td>{{ $webhook->type }}</td><td><code class="a-code">{{ $webhook->external_id }}</code></td><td><x-admin.badge :status="$webhook->status" />@if($webhook->last_error)<small style="color:var(--a-danger)">{{ str($webhook->last_error)->limit(90) }}</small>@endif</td><td class="num">{{ $webhook->attempts }}</td><td class="nowrap">{{ $webhook->created_at->format('M j, Y g:i A') }}</td></tr>
      @empty
        <tr><td colspan="6"><div class="a-table-empty"><strong>No webhook events yet</strong>Stripe events will appear here once the webhook endpoint is registered.</div></td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div></section>
<?php page_end(); ?>
