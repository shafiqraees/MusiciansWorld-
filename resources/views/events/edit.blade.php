@extends('account.layout', ['title' => $event->title, 'current' => 'events'])

<?php $editable = in_array($event->status, ['draft', 'changes_requested', 'rejected'], true); $tz = $event->resource->timezone ?? 'America/Phoenix'; ?>

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker"><a href="{{ route('account.events') }}" style="color:inherit">My Events</a> · {{ $event->reference }}</p>
    <h1>{{ $event->title }}</h1>
    <p>{{ $event->starts_at?->timezone($tz)->format('l, F j, Y · g:i A T') }} · {{ $event->venue_name ?: ($event->resource->name ?? a_label($event->location_type)) }} · platform fee {{ \App\Support\Finance::percent((int) $event->platform_fee_bps) }}</p>
  </div>
  <div class="acct-head-actions">
    {!! acct_badge($event->status) !!}
    <a class="btn btn-ghost" href="{{ route('account.events.show', $event) }}">Sales &amp; transactions</a>
    @if(in_array($event->status, ['approved', 'published'], true))<a class="btn btn-ghost" href="{{ route('events.show', $event->slug) }}">Public page</a>@endif
  </div>
</div>

@if($event->review_notes && in_array($event->status, ['changes_requested', 'rejected'], true))
  <div class="acct-note is-warn" style="display:block"><strong>MWCN review notes:</strong> {{ $event->review_notes }}</div>
@elseif($event->status === 'submitted')
  <div class="acct-note is-warn">MWCN is reviewing this event. Fields are locked until the review is complete.</div>
@elseif(!$editable)
  <div class="acct-note is-ok">This event is {{ a_label($event->status) }}. Contact MWCN if you need to change details.</div>
@endif

@include('events.partials.form', ['action' => route('organizer.events.update', $event), 'method' => 'PUT', 'event' => $event])

@if($editable)
  <div class="acct-panel">
    <div class="acct-panel-head">
      <div><h2>Submit to MWCN</h2><p class="acct-lead" style="margin-top:.3rem">Save your changes first. MWCN reviews the details, venue schedule and ticketing, then publishes the event page.</p></div>
      <form method="post" action="{{ route('organizer.events.submit', $event) }}">@csrf<button class="btn btn-mark" type="submit" @disabled($event->ticketTypes->isEmpty())>Submit for review</button></form>
    </div>
    @if($event->ticketTypes->isEmpty())<p class="acct-empty">Add at least one ticket type and save before submitting.</p>@endif
  </div>
@endif
@endsection
