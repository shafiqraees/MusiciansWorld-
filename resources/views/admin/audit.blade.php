<?php page_start('Audit trail — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head"><div><p class="a-kicker">Data integrity</p><h2>Audit trail</h2><p>Every change made through the portal with the actor, reason and before/after snapshot. This page is hidden from the navigation for now.</p></div></div>
  <section class="a-card"><div class="a-card-body">
    <div class="a-timeline">
      @forelse($logs as $log)
        <div class="a-timeline-item"><i></i><div><strong>{{ a_label($log->action) }}</strong><small>{{ $log->created_at->format('M j, Y g:i:s A') }} · {{ $log->actor?->name ?? 'System' }} · {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}{{ $log->ip_address ? ' · '.$log->ip_address : '' }}</small>@if($log->reason)<small>Reason: {{ $log->reason }}</small>@endif<details><summary>Before / after</summary><pre>{{ json_encode(['before' => $log->before, 'after' => $log->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></div></div>
      @empty<div class="a-empty"><strong>No audit entries</strong>Changes will be recorded here.</div>@endforelse
    </div>
  </div></section>
  {{ $logs->links('admin.partials.pagination') }}
</div></section>
<?php page_end(); ?>
