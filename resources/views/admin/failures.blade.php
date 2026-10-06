<?php page_start('Automation failures — MWCN ERP'); ?>
<section class="section"><div class="wrap">
  <div class="a-page-head"><div><p class="a-kicker">Automation</p><h2>Integration jobs</h2><p>Background automation runs and their most recent errors. This page is hidden from the navigation for now.</p></div></div>
  <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Integration</th><th>Action</th><th>Status</th><th class="num">Attempts</th><th>Last error</th><th>Next attempt</th><th class="actions">Actions</th></tr></thead><tbody>
    @forelse($jobs as $job)<tr><td><strong>{{ a_label($job->integration) }}</strong></td><td>{{ $job->action }}</td><td><x-admin.badge :status="$job->status" /></td><td class="num">{{ $job->attempts }}</td><td><small style="white-space:normal;max-width:420px;color:var(--a-danger)">{{ $job->last_error }}</small></td><td class="nowrap">{{ $job->next_attempt_at?->format('M j, g:i A') ?? '—' }}</td><td class="actions"><form method="post" action="{{ route('admin.failures.retry', $job) }}">@csrf<button class="a-btn ghost sm" type="submit"><svg><use href="#ai-refresh"></use></svg>Retry</button></form></td></tr>
    @empty<tr><td colspan="7"><div class="a-table-empty"><strong>No integration jobs</strong>Nothing has run yet.</div></td></tr>@endforelse
  </tbody></table></div>
  {{ $jobs->links('admin.partials.pagination') }}
</div></section>
<?php page_end(); ?>
