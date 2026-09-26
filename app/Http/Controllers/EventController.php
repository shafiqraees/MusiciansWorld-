<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\PlatformSetting;
use App\Models\Resource;
use App\Models\Role;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('ticketTypes')->where('status', 'published')->where('ends_at', '>', now())->orderBy('starts_at')->get();
        return view('events.index', compact('events'));
    }

    public function show(string $slug)
    {
        $event = Event::with('ticketTypes')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        return view('events.show', compact('event'));
    }

    public function create(Request $request)
    {
        $profile = OrganizerProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['display_name' => $request->user()->name, 'status' => 'eligible']
        );
        $request->user()->roles()->syncWithoutDetaching([Role::where('name', 'organizer')->value('id')]);
        $services = \App\Models\EventService::where('is_active', true)->get();
        return view('events.create', compact('profile', 'services'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $event = DB::transaction(function () use ($request, $data) {
            $resourceId = $data['location_type'] === 'soundtunnel' ? Resource::where('slug', 'soundtunnel')->value('id') : null;
            $membership = $request->user()->activeMembership();
            $fee = $membership?->plan?->slug === 'founder'
                ? PlatformSetting::valueOf('promoter.founder.bps', 300)
                : PlatformSetting::valueOf('promoter.community.bps', 500);
            $event = Event::create([
                ...$data,
                'reference' => 'EVT-'.strtoupper(Str::random(10)),
                'organizer_id' => $request->user()->id,
                'resource_id' => $resourceId,
                'slug' => Str::slug($data['title']).'-'.strtolower(Str::random(5)),
                'status' => 'draft',
                'platform_fee_bps' => $fee,
            ]);
            $this->syncTicketTypes($event, $request->input('tickets', []));
            $this->syncServices($event, $request->input('services', []));
            return $event;
        });
        return redirect()->route('organizer.events.edit', $event)->with('success', 'Event draft saved.');
    }

    public function edit(Request $request, Event $event)
    {
        abort_unless($event->organizer_id === $request->user()->id, 403);
        $event->load('ticketTypes', 'services', 'settlement');
        $services = \App\Models\EventService::where('is_active', true)->get();
        return view('events.edit', compact('event', 'services'));
    }

    public function update(Request $request, Event $event)
    {
        abort_unless($event->organizer_id === $request->user()->id && in_array($event->status, ['draft', 'changes_requested'], true), 403);
        $before = $event->toArray();
        $event->update($this->validated($request));
        $this->syncTicketTypes($event, $request->input('tickets', []));
        $this->syncServices($event, $request->input('services', []));
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'event.updated', 'auditable_type' => Event::class, 'auditable_id' => $event->id, 'before' => $before, 'after' => $event->fresh()->toArray(), 'ip_address' => $request->ip()]);
        return back()->with('success', 'Event draft updated.');
    }

    public function submit(Request $request, Event $event)
    {
        abort_unless($event->organizer_id === $request->user()->id && in_array($event->status, ['draft', 'changes_requested'], true), 403);
        abort_if($event->ticketTypes()->count() === 0, 422, 'At least one ticket type is required.');
        $event->update(['status' => 'submitted', 'submitted_at' => now()]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'event.submitted', 'auditable_type' => Event::class, 'auditable_id' => $event->id, 'after' => $event->fresh()->toArray(), 'ip_address' => $request->ip()]);
        return back()->with('success', 'Event submitted to MWCN for review.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'], 'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'], 'location_type' => ['required', 'in:soundtunnel,external,virtual'],
            'venue_name' => ['nullable', 'string'], 'venue_address' => ['nullable', 'string'],
            'virtual_access' => ['nullable', 'string'], 'doors_at' => ['nullable', 'date'],
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'setup_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'], 'teardown_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'], 'visibility' => ['required', 'in:public,private'],
        ]);
    }

    private function syncTicketTypes(Event $event, array $tickets): void
    {
        foreach ($tickets as $row) {
            if (empty($row['name']) || !isset($row['quantity'])) {
                continue;
            }
            $event->ticketTypes()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                [
                    'name' => $row['name'], 'description' => $row['description'] ?? null,
                    'price_cents' => (int) round(((float) ($row['price'] ?? 0)) * 100),
                    'quantity' => (int) $row['quantity'], 'per_order_limit' => (int) ($row['per_order_limit'] ?? 10),
                    'sales_start_at' => $row['sales_start_at'] ?? null, 'sales_end_at' => $row['sales_end_at'] ?? null,
                    'status' => 'active', 'is_refundable' => !empty($row['is_refundable']),
                ]
            );
        }
    }

    private function syncServices(Event $event, array $services): void
    {
        $sync = [];
        foreach ($services as $serviceId => $quantity) {
            $service = \App\Models\EventService::find($serviceId);
            if ($service && (int) $quantity > 0) {
                $sync[$serviceId] = ['quantity' => (int) $quantity, 'unit_price_cents' => $service->price_cents, 'status' => 'selected'];
            }
        }
        $event->services()->sync($sync);
    }
}
