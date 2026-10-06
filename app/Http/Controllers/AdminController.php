<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\RecordsAudit;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\EventService;
use App\Models\FinancialTransaction;
use App\Models\FormSubmission;
use App\Models\IntegrationJob;
use App\Models\OrganizerSettlement;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\MicrosoftCalendarService;
use App\Services\SettlementService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class AdminController extends Controller
{
    use RecordsAudit;

    public const BOOKING_STATUSES = ['pending_payment' => 'Pending payment', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'admin_hold' => 'Admin hold', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'refunded' => 'Refunded', 'failed' => 'Failed'];
    public const PAYMENT_STATUSES = ['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'refunded' => 'Refunded'];
    public const EVENT_STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'changes_requested' => 'Changes requested', 'approved' => 'Approved', 'published' => 'Published', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'completed' => 'Completed', 'closeout' => 'Closeout'];

    public function dashboard()
    {
        $metrics = [
            'customers' => User::count(),
            'new_customers' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'active_memberships' => DB::table('customer_memberships')->whereIn('status', ['active', 'trialing', 'grace'])->count(),
            'upcoming_bookings' => Booking::where('starts_at', '>', now())->where('status', 'confirmed')->count(),
            'pending_events' => Event::where('status', 'submitted')->count(),
            'revenue_cents' => FinancialTransaction::where('status', 'succeeded')->where('amount_cents', '>', 0)
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount_cents'),
        ];
        $recentUsers = User::with('roles', 'memberships.plan')->latest()->limit(6)->get();
        $recentBookings = Booking::with('resource')->latest()->limit(6)->get();
        $pendingEventRows = Event::with('organizer')->where('status', 'submitted')->oldest('submitted_at')->limit(5)->get();
        $recentSubmissions = FormSubmission::latest()->limit(6)->get();
        $health = [
            'Stripe payments' => config('services.stripe.secret') ? 'connected' : 'not configured',
            'Mail delivery' => config('mail.default') === 'smtp' ? 'connected' : config('mail.default'),
            'Microsoft Calendar' => config('services.microsoft.client_id') ? 'connected' : 'not configured',
            'Automation queue' => IntegrationJob::where('status', 'failed')->exists() ? 'attention needed' : 'healthy',
        ];
        return view('admin.dashboard', compact('metrics', 'recentUsers', 'recentBookings', 'pendingEventRows', 'recentSubmissions', 'health'));
    }

    public function bookings(Request $request)
    {
        $query = Booking::with('resource', 'user')->latest('starts_at');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $search)->orWhere('customer_name', 'like', $search)->orWhere('customer_email', 'like', $search)->orWhere('customer_phone', 'like', $search));
        }
        if ($request->filled('payment')) {
            $query->where('payment_status', $request->string('payment'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('resource')) {
            $query->where('resource_id', (int) $request->input('resource'));
        }
        if ($request->filled('from')) {
            $query->where('starts_at', '>=', \Carbon\Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('starts_at', '<=', \Carbon\Carbon::parse($request->input('to'))->endOfDay());
        }
        if ($request->input('when') === 'upcoming') {
            $query->where('starts_at', '>=', now());
        } elseif ($request->input('when') === 'past') {
            $query->where('starts_at', '<', now());
        }

        $stats = [
            'upcoming' => Booking::where('starts_at', '>=', now())->whereIn('status', ['confirmed', 'paid'])->count(),
            'pending' => Booking::where('payment_status', 'pending')->count(),
            'revenue_cents' => (int) Booking::where('payment_status', 'paid')->where('starts_at', '>=', now()->startOfMonth())->sum('total_cents'),
            'sync_issues' => Booking::where('calendar_sync_status', 'failed')->count(),
        ];

        return view('admin.bookings', [
            'bookings' => $query->paginate(25)->withQueryString(),
            'resources' => Resource::orderBy('name')->get(),
            'stats' => $stats,
            'statuses' => self::BOOKING_STATUSES,
            'paymentStatuses' => self::PAYMENT_STATUSES,
        ]);
    }

    public function updateBooking(Request $request, Booking $booking, MicrosoftCalendarService $calendar)
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(self::PAYMENT_STATUSES))],
            'status' => ['nullable', Rule::in(array_keys(self::BOOKING_STATUSES))],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $booking->toArray();
        $paymentChanged = $booking->payment_status !== $data['payment_status'];
        $status = $data['status'] ?? null;
        if ($paymentChanged && !$status) {
            $status = $data['payment_status'] === 'paid' ? 'confirmed' : ($data['payment_status'] === 'pending' ? $booking->status : $data['payment_status']);
        }
        $booking->update([
            'payment_status' => $data['payment_status'],
            'status' => $status ?: $booking->status,
            'customer_name' => $data['customer_name'],
            'customer_email' => strtolower($data['customer_email']),
            'customer_phone' => $data['customer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        if ($paymentChanged && $data['payment_status'] === 'paid') {
            $calendar->syncBooking($booking->fresh('resource'));
        } elseif ($paymentChanged && $data['payment_status'] === 'cancelled') {
            $calendar->cancelBooking($booking->fresh('resource'));
        }
        $this->audit($request, $paymentChanged ? 'booking.payment_status' : 'booking.updated', $booking, $before, $data['reason']);
        return back()->with('success', 'Booking '.$booking->reference.' updated.');
    }

    public function events(Request $request)
    {
        $query = Event::with('organizer', 'resource', 'settlement', 'reviewer')->withCount('ticketTypes')->withSum('ticketTypes as tickets_sold', 'sold_count')->latest('starts_at');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($q) => $q->where('title', 'like', $search)->orWhere('reference', 'like', $search)->orWhereHas('organizer', fn ($o) => $o->where('name', 'like', $search)->orWhere('email', 'like', $search)));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('location')) {
            $query->where('location_type', $request->string('location'));
        }
        if ($request->input('when') === 'upcoming') {
            $query->where('starts_at', '>=', now());
        } elseif ($request->input('when') === 'past') {
            $query->where('starts_at', '<', now());
        }

        $stats = [
            'pending' => Event::where('status', 'submitted')->count(),
            'published' => Event::where('status', 'published')->where('ends_at', '>', now())->count(),
            'tickets' => (int) DB::table('ticket_types')->sum('sold_count'),
            'payouts' => OrganizerSettlement::whereIn('status', ['pending_closeout', 'ready', 'approved'])->count(),
        ];

        return view('admin.events', [
            'events' => $query->paginate(25)->withQueryString(),
            'services' => EventService::where('is_active', true)->orderBy('name')->get(),
            'stats' => $stats,
            'statuses' => self::EVENT_STATUSES,
            'locations' => ['soundtunnel' => 'SoundTunnel', 'external' => 'External venue', 'virtual' => 'Virtual'],
        ]);
    }

    public function updateEvent(Request $request, Event $event)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'location_type' => ['required', 'in:soundtunnel,external,virtual'],
            'venue_name' => ['nullable', 'string', 'max:190'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'virtual_access' => ['nullable', 'string', 'max:500'],
            'doors_at' => ['nullable', 'date'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'setup_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'teardown_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'visibility' => ['required', 'in:public,private'],
            'platform_fee_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $event->toArray();
        $resourceId = $data['location_type'] === 'soundtunnel' ? Resource::where('slug', 'soundtunnel')->value('id') : null;
        $event->update(collect($data)->except('reason')->all() + [
            'resource_id' => $resourceId,
            'setup_minutes' => (int) ($data['setup_minutes'] ?? 0),
            'teardown_minutes' => (int) ($data['teardown_minutes'] ?? 0),
        ]);
        $this->audit($request, 'event.updated_by_admin', $event, $before, $data['reason']);
        return back()->with('success', 'Event "'.$event->title.'" updated.');
    }

    public function reviewEvent(Request $request, Event $event)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject,request_changes,suspend,publish,complete'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $before = $event->toArray();
        try {
            DB::transaction(function () use ($request, $event, $data) {
                $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                if ($data['action'] === 'approve' && $event->resource_id) {
                    $start = $event->starts_at->copy()->subMinutes($event->setup_minutes);
                    $end = $event->ends_at->copy()->addMinutes($event->teardown_minutes);
                    $bookingConflict = Booking::where('resource_id', $event->resource_id)
                        ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                        ->whereIn('status', ['confirmed', 'paid', 'admin_hold'])->exists();
                    $eventConflict = Event::where('resource_id', $event->resource_id)->whereKeyNot($event->id)
                        ->whereIn('status', ['approved', 'published', 'completed', 'closeout'])
                        ->where('starts_at', '<', $end->copy()->addDay())->where('ends_at', '>', $start->copy()->subDay())
                        ->get()->contains(fn (Event $other) =>
                            $other->starts_at->copy()->subMinutes($other->setup_minutes)->lt($end)
                            && $other->ends_at->copy()->addMinutes($other->teardown_minutes)->gt($start)
                        );
                    if ($bookingConflict || $eventConflict) {
                        throw new RuntimeException('SoundTunnel is unavailable during this event window.');
                    }
                }
                $status = match ($data['action']) {
                    'approve' => 'approved', 'reject' => 'rejected', 'request_changes' => 'changes_requested',
                    'suspend' => 'suspended', 'publish' => 'published', 'complete' => 'completed',
                };
                if ($status === 'published' && $event->status !== 'approved') {
                    throw new RuntimeException('Approve the event before publishing.');
                }
                $event->update([
                    'status' => $status, 'review_notes' => $data['notes'] ?? null, 'reviewed_by' => $request->user()->id,
                    'approved_at' => $status === 'approved' ? now() : $event->approved_at,
                    'published_at' => $status === 'published' ? now() : $event->published_at,
                ]);
                if ($status === 'completed') {
                    OrganizerSettlement::firstOrCreate(['event_id' => $event->id], ['status' => 'pending_closeout']);
                }
            }, 3);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()])->with('modal', 'event-'.$event->id);
        }
        $this->audit($request, 'event.'.$data['action'], $event, $before, $data['notes'] ?? null);
        return back()->with('success', 'Event "'.$event->title.'" is now '.str_replace('_', ' ', $event->fresh()->status).'.');
    }

    public function failures()
    {
        return view('admin.failures', ['jobs' => IntegrationJob::latest()->paginate(100)]);
    }

    public function reports(Request $request)
    {
        $from = \Carbon\CarbonImmutable::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = \Carbon\CarbonImmutable::parse($request->query('to', now()->toDateString()))->endOfDay();
        $revenue = FinancialTransaction::selectRaw('type, SUM(amount_cents) amount_cents')
            ->where('status', 'succeeded')->whereBetween('created_at', [$from, $to])
            ->groupBy('type')->orderBy('type')->get();
        $bookings = Booking::selectRaw('status, COUNT(*) total')->whereBetween('created_at', [$from, $to])->groupBy('status')->get();
        $memberships = DB::table('customer_memberships')->join('membership_plans', 'membership_plans.id', '=', 'customer_memberships.membership_plan_id')
            ->selectRaw('membership_plans.name, customer_memberships.status, COUNT(*) total')->groupBy('membership_plans.name', 'customer_memberships.status')->get();
        $events = Event::withCount(['orders', 'ticketTypes'])->with('settlement')->whereBetween('starts_at', [$from, $to])->latest()->get();
        $totals = [
            'gross' => (int) ($revenue->firstWhere('type', 'gross_ticket_revenue')?->amount_cents ?? 0),
            'platform' => (int) ($revenue->firstWhere('type', 'mwcn_platform_fee')?->amount_cents ?? 0),
            'organizer' => (int) ($revenue->firstWhere('type', 'organizer_proceeds')?->amount_cents ?? 0),
            'refunds' => (int) ($revenue->firstWhere('type', 'refund')?->amount_cents ?? 0),
        ];
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($revenue) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Category', 'Amount USD']);
                foreach ($revenue as $row) {
                    fputcsv($out, [$row->type, number_format($row->amount_cents / 100, 2, '.', '')]);
                }
                fclose($out);
            }, 'mwcn-financial-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }
        return view('admin.reports', compact('revenue', 'bookings', 'memberships', 'events', 'totals', 'from', 'to'));
    }

    public function auditLogs()
    {
        return view('admin.audit', ['logs' => AuditLog::with('actor')->latest()->paginate(100)]);
    }

    public function roles()
    {
        return view('admin.roles', [
            'roles' => Role::with('permissionRecords')->withCount('users')->orderBy('label')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('label')->get()->groupBy('group'),
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        abort_if($role->name === 'super_admin' && !$request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = ['label' => $role->label, 'permissions' => $role->permissionRecords()->pluck('permissions.id')->all()];
        DB::transaction(function () use ($role, $data) {
            $role->update(['label' => $data['label']]);
            $role->permissionRecords()->sync($data['permissions'] ?? []);
        });
        $this->audit($request, 'role.permissions_synced', $role, $before, $data['reason'], ['label' => $role->fresh()->label, 'permissions' => $role->permissionRecords()->pluck('permissions.id')->all()]);
        return back()->with('success', $role->label.' permissions synchronized.');
    }

    public function submissions(Request $request)
    {
        $query = FormSubmission::latest();
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        $types = [
            'dj_school_inquiry' => 'DJ School',
            'soundtunnel_rental' => 'SoundTunnel',
            'dj_enrollment' => 'DJ enrollment',
            'dj_lesson_inquiry' => 'Private lesson',
            'contact' => 'Contact',
            'membership_waitlist' => 'Membership',
        ];
        return view('admin.submissions', [
            'submissions' => $query->paginate(40)->withQueryString(),
            'types' => $types,
            'activeType' => (string) $request->query('type', ''),
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->query('type', 'bookings');
        return response()->streamDownload(function () use ($type) {
            $out = fopen('php://output', 'w');
            if ($type === 'members') {
                fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Account type', 'Status', 'Roles', 'Joined']);
                User::with('roles')->orderBy('id')->each(fn ($row) => fputcsv($out, [$row->id, $row->name, $row->email, $row->phone, $row->account_type, $row->status, $row->roles->pluck('label')->join('; '), $row->created_at]));
            } elseif ($type === 'leads') {
                fputcsv($out, ['ID', 'Type', 'Status', 'Name', 'Email', 'Phone', 'Created']);
                FormSubmission::orderBy('id')->each(fn ($row) => fputcsv($out, [$row->id, $row->type, $row->status, $row->name, $row->email, $row->phone, $row->created_at]));
            } elseif ($type === 'events') {
                fputcsv($out, ['Reference', 'Title', 'Organizer', 'Starts', 'Ends', 'Location', 'Capacity', 'Status']);
                Event::with('organizer')->orderBy('id')->each(fn ($row) => fputcsv($out, [$row->reference, $row->title, $row->organizer?->name, $row->starts_at, $row->ends_at, $row->location_type, $row->capacity, $row->status]));
            } else {
                fputcsv($out, ['Reference', 'Resource', 'Start', 'End', 'Customer', 'Email', 'Total', 'Status', 'Payment']);
                Booking::with('resource')->orderBy('id')->each(fn ($row) => fputcsv($out, [$row->reference, $row->resource->name, $row->starts_at, $row->ends_at, $row->customer_name, $row->customer_email, number_format($row->total_cents / 100, 2, '.', ''), $row->status, $row->payment_status]));
            }
            fclose($out);
        }, 'mwcn-'.$type.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function retryFailure(IntegrationJob $job)
    {
        $job->update(['status' => 'pending', 'next_attempt_at' => now(), 'last_error' => null]);
        return back()->with('success', 'Integration retry queued.');
    }

    public function calculateSettlement(Event $event, SettlementService $settlements)
    {
        $settlements->calculate($event->load('orders', 'resource', 'services'));
        return back()->with('success', 'Settlement recalculated from ledger records.');
    }

    public function transferSettlement(Request $request, OrganizerSettlement $settlement, SettlementService $settlements, StripeService $stripe)
    {
        $request->validate(['confirm' => ['accepted']]);
        try {
            $settlements->transfer($settlement, $request->user()->id, $stripe);
            return back()->with('success', 'Organizer transfer submitted to Stripe.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['settlement' => $exception->getMessage()]);
        }
    }
}
