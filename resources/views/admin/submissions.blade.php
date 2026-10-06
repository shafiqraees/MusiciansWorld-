<?php
page_start('Leads & submissions — MWCN ERP');
$labels = [
    'name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'state' => 'State', 'zip' => 'ZIP code',
    'age' => 'Age', 'parentName' => 'Parent / guardian name', 'parentEmail' => 'Parent / guardian email', 'parentPhone' => 'Parent / guardian phone',
    'program' => 'DJ School option', 'experience' => 'Experience level', 'hasEquipment' => 'Owns DJ equipment', 'equipment' => 'Current equipment',
    'software' => 'DJ software', 'skills' => 'Skills to develop', 'bestTimes' => 'Best time of day', 'bestDays' => 'Best days',
    'preparingEvent' => 'Preparing for an event', 'eventDetails' => 'Event details', 'goals' => 'Hoping to learn', 'referral' => 'How they heard about us',
    'additional' => 'Additional information', 'acknowledgment' => 'Acknowledgment', 'organization' => 'Organization', 'eventName' => 'Event name',
    'eventType' => 'Event type', 'date' => 'Preferred date', 'alternateDate' => 'Alternate date', 'startTime' => 'Start time', 'duration' => 'Duration',
    'headcount' => 'Attendance', 'audience' => 'Audience', 'ticketed' => 'Ticketed', 'ticketPrice' => 'Ticket price', 'services' => 'Venue services',
    'alcohol' => 'Alcohol', 'budget' => 'Budget', 'productionNotes' => 'Production notes', 'message' => 'Message', 'comments' => 'Comments',
    'address' => 'Address', 'plan' => 'Plan',
];
$humanize = static function (string $key) use ($labels): string {
    if (isset($labels[$key])) return $labels[$key];
    return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $key)));
};
?>
<section class="section"><div class="wrap">
  <div class="a-page-head">
    <div>
      <p class="a-kicker">Customer requests</p>
      <h2>Leads & submissions</h2>
      <p>DJ School inquiries and SoundTunnel applications, with every answer saved from the form.</p>
    </div>
    <div class="a-page-actions"><a class="a-btn ghost" href="{{ route('admin.export', ['type' => 'leads']) }}"><svg><use href="#ai-download"></use></svg>Export CSV</a></div>
  </div>

  <div class="a-chips">
    <a class="a-chip {{ $activeType === '' ? 'is-active' : '' }}" href="{{ route('admin.submissions') }}">All</a>
    @foreach($types as $value => $label)
      <a class="a-chip {{ $activeType === $value ? 'is-active' : '' }}" href="{{ route('admin.submissions', ['type' => $value]) }}">{{ $label }}</a>
    @endforeach
  </div>

  @forelse($submissions as $submission)
    <details class="admin-submission">
      <summary>
        <span><span class="a-badge info plain">{{ $types[$submission->type] ?? ucwords(str_replace('_', ' ', $submission->type)) }}</span> <x-admin.badge :status="$submission->status" /></span>
        <span>
          <strong>{{ $submission->name }}</strong>
          <small>{{ $submission->email }}{{ $submission->phone ? ' · '.$submission->phone : '' }}</small>
        </span>
        <span>{{ $submission->created_at->format('M j, Y g:i A') }}</span>
      </summary>
      <dl class="admin-answer-grid">
        @foreach(($submission->data ?: []) as $key => $value)
          @continue(in_array($key, ['website', 'acknowledgment'], true) || $value === null || $value === '')
          <dt>{{ $humanize($key) }}</dt>
          <dd>{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
        @endforeach
        @if(empty($submission->data))
          <dt>Message</dt>
          <dd>{{ $submission->message ?: 'No extra answers were stored.' }}</dd>
        @endif
      </dl>
    </details>
  @empty
    <div class="a-card"><div class="a-empty"><strong>No submissions in this view yet</strong>New leads will appear here as forms are submitted.</div></div>
  @endforelse

  {{ $submissions->links('admin.partials.pagination') }}
</div></section>
<?php page_end(); ?>
