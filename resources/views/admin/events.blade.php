<?php page_start('MWCN ERP — Events'); ?>
<section class="section"><div class="wrap">
<p class="kicker">Approval queue</p><h1 class="display mt-3 text-5xl">Events</h1>

@if(session('success'))<p class="mt-5 text-green-300">{{ session('success') }}</p>@endif
@if($errors->any())<p class="mt-5 text-red-300">{{ $errors->first() }}</p>@endif
<div class="mt-8 grid gap-5">@foreach($events as $event)
<article class="card p-6"><div class="flex flex-wrap justify-between gap-4"><div>
<p class="kicker">{{ ucfirst(str_replace('_',' ',$event->status)) }}</p>
<h2 class="display mt-2 text-3xl">{{ $event->title }}</h2>
<p class="mt-2 text-dim">{{ $event->organizer->name }} · {{ $event->starts_at->format('M j, Y g:i A') }} · {{ $event->location_type }}</p>
@if($event->settlement)<div class="mt-4 text-sm"><p>Gross ${{ number_format($event->settlement->gross_ticket_revenue_cents/100,2) }} · Platform ${{ number_format($event->settlement->platform_fees_cents/100,2) }} · Organizer ${{ number_format($event->settlement->organizer_proceeds_cents/100,2) }}</p><p class="text-dim">Payout: {{ ucfirst(str_replace('_',' ',$event->settlement->status)) }}</p></div>@endif
<div class="mt-4 flex flex-wrap gap-2"><form method="post" action="{{ route('admin.settlements.calculate',$event) }}">@csrf<button class="btn btn-ghost">Calculate settlement</button></form>
@if($event->settlement)<form method="post" action="{{ route('admin.settlements.transfer',$event->settlement) }}">@csrf<input type="hidden" name="confirm" value="1"><button class="btn btn-ghost">Approve Stripe transfer</button></form>@endif</div>
</div><form method="post" action="{{ route('admin.events.review',$event) }}" class="grid min-w-64 gap-2">@csrf @method('PATCH')<select class="input" name="action"><option value="approve">Approve</option><option value="request_changes">Request changes</option><option value="reject">Reject</option><option value="publish">Publish</option><option value="suspend">Suspend</option><option value="complete">Complete</option></select><textarea class="input" name="notes" placeholder="Review notes"></textarea><button class="btn btn-mark">Apply</button></form></div></article>
@endforeach</div>{{ $events->links() }}</div></section><?php page_end(); ?>
