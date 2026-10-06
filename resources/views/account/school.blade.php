@extends('account.layout', ['title' => 'DJ School', 'current' => 'school'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker">DJ School</p>
    <h1>Programs &amp; lessons</h1>
    <p>Your enrollments, assigned instructors, payment status and any inquiries you've sent to the school team.</p>
  </div>
  <div class="acct-head-actions">
    <a class="btn btn-ghost" href="/dj-school">Browse programs</a>
    <a class="btn btn-mark" href="/book/dj-lesson">Book a private lesson</a>
  </div>
</div>

<div class="acct-stats">
  <div class="acct-stat is-mark"><small>Enrollments</small><strong>{{ $registrations->count() }}</strong><span>all programs</span></div>
  <div class="acct-stat"><small>Active</small><strong>{{ $registrations->whereIn('status', ['active', 'confirmed', 'enrolled'])->count() }}</strong><span>in progress</span></div>
  <div class="acct-stat"><small>Awaiting payment</small><strong>{{ $registrations->where('payment_status', 'pending')->count() }}</strong><span>needs attention</span></div>
  <div class="acct-stat"><small>Inquiries</small><strong>{{ $inquiries->count() }}</strong><span>sent to the school</span></div>
</div>

<div class="acct-panel">
  <h2>Registrations</h2>
  @forelse($registrations as $registration)
    <div class="acct-item" style="margin-top:.85rem">
      <div class="acct-item-top">
        <div>
          <strong>{{ $registration->program?->name ?? 'Program' }}</strong>
          <span>{{ a_label($registration->program?->type ?? 'program') }}{{ is_string($registration->program?->schedule) && $registration->program->schedule !== '' ? ' · '.$registration->program->schedule : '' }} · registered {{ $registration->created_at->format('M j, Y') }}</span>
        </div>
        <div class="acct-pills">{!! acct_badge($registration->status) !!} {!! acct_badge($registration->payment_status, 'Payment '.a_label($registration->payment_status)) !!}</div>
      </div>
      <dl class="acct-dl">
        <div><dt>Instructor</dt><dd>{{ $registration->instructor?->name ?? 'To be assigned' }}</dd></div>
        <div><dt>Program fee</dt><dd>{{ $registration->program?->price_cents ? a_money($registration->program->price_cents) : 'Included' }}</dd></div>
        @if($registration->program?->description)<div><dt>About</dt><dd>{{ $registration->program->description }}</dd></div>@endif
        @if($registration->notes)<div><dt>Notes from the school</dt><dd style="white-space:pre-line">{{ $registration->notes }}</dd></div>@endif
      </dl>
    </div>
  @empty
    <p class="acct-empty">You're not enrolled in a program yet. <a href="/dj-school">Explore DJ School</a>.</p>
  @endforelse
</div>

<div class="acct-panel">
  <h2>Inquiries &amp; requests</h2>
  @if($inquiries->isEmpty())
    <p class="acct-empty">No DJ School inquiries on file.</p>
  @else
    <ul class="acct-list">
      @foreach($inquiries as $inquiry)
        <li>
          <div class="acct-item-top"><strong>{{ a_label(str_replace(['dj_', '_inquiry'], ['DJ ', ' inquiry'], $inquiry->type)) }}</strong>{!! acct_badge($inquiry->status) !!}</div>
          <span>{{ $inquiry->created_at->format('M j, Y') }}{{ $inquiry->event_date ? ' · preferred '.\Illuminate\Support\Carbon::parse($inquiry->event_date)->format('M j, Y') : '' }}</span>
          @if($inquiry->message)<span>{{ \Illuminate\Support\Str::limit($inquiry->message, 220) }}</span>@endif
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
