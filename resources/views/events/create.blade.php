@extends('account.layout', ['title' => 'Host an event', 'current' => 'events'])

@section('content')
<div class="acct-head">
  <div>
    <p class="kicker"><a href="{{ route('account.events') }}" style="color:inherit">My Events</a> · Promoter platform</p>
    <h1>Create your event</h1>
    <p>Save a draft, configure tickets and services, then submit it to MWCN for approval. Your platform fee tier is {{ \App\Support\Finance::percent($feeBps) }} of paid ticket sales{{ $feeBps > 300 ? ' — Founding Members pay 3%' : '' }}.</p>
  </div>
  <a class="btn btn-ghost" href="{{ route('account.events') }}">Back to My Events</a>
</div>
@include('events.partials.form', ['action' => route('organizer.events.store'), 'method' => 'POST', 'event' => null])
@endsection
