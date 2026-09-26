<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AvailabilityBlock;
use App\Models\Benefit;
use App\Models\Booking;
use App\Models\Event;
use App\Models\IntegrationJob;
use App\Models\FinancialTransaction;
use App\Models\FormSubmission;
use App\Models\MembershipPlan;
use App\Models\PricingRule;
use App\Models\OrganizerSettlement;
use App\Models\PlatformSetting;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\SettlementService;
use App\Services\StripeService;
use App\Services\MicrosoftCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminController extends Controller
{
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
        $query = Booking::with('resource', 'user')->latest();
        if ($request->filled('payment')) {
            $query->where('payment_status', $request->string('payment'));
        }
        return view('admin.bookings', ['bookings' => $query->paginate(50)]);
    }

    public function bookingStatus(Request $request, Booking $booking, MicrosoftCalendarService $calendar)
    {
        $data = $request->validate(['payment_status' => ['required', 'in:paid,pending,failed,cancelled,expired,refunded'], 'reason' => ['required', 'string', 'max:500']]);
        $before = $booking->toArray();
        $booking->update([
            'payment_status' => $data['payment_status'],
            'status' => $data['payment_status'] === 'paid' ? 'confirmed' : ($data['payment_status'] === 'pending' ? $booking->status : $data['payment_status']),
        ]);
        if ($data['payment_status'] === 'paid') {
            $calendar->syncBooking($booking->fresh('resource'));
        } elseif ($data['payment_status'] === 'cancelled') {
            $calendar->cancelBooking($booking->fresh('resource'));
        }
        $this->audit($request, 'booking.payment_status', $booking, $before, $data['reason']);
        return back()->with('success', 'Booking payment status updated.');
    }

    public function events()
    {
        return view('admin.events', ['events' => Event::with('organizer', 'resource', 'settlement')->latest()->paginate(50)]);
    }

    public function reviewEvent(Request $request, Event $event)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject,request_changes,suspend,publish,complete'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $before = $event->toArray();
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
            abort_if($status === 'published' && $event->status !== 'approved', 422, 'Approve the event before publishing.');
            $event->update([
                'status' => $status, 'review_notes' => $data['notes'] ?? null, 'reviewed_by' => $request->user()->id,
                'approved_at' => $status === 'approved' ? now() : $event->approved_at,
                'published_at' => $status === 'published' ? now() : $event->published_at,
            ]);
            if ($status === 'completed') {
                OrganizerSettlement::firstOrCreate(['event_id' => $event->id], ['status' => 'pending_closeout']);
            }
        }, 3);
        $this->audit($request, 'event.'.$data['action'], $event, $before, $data['notes'] ?? null);
        return back()->with('success', 'Event status updated.');
    }

    public function settings()
    {
        return view('admin.settings', [
            'settings' => PlatformSetting::orderBy('group')->orderBy('key')->get(),
            'plans' => MembershipPlan::with('benefits')->orderBy('sort_order')->get(),
            'benefits' => Benefit::orderBy('name')->get(),
            'resources' => Resource::with('pricingRules')->get(),
            'holds' => AvailabilityBlock::with('resource')->where('ends_at', '>', now())->orderBy('starts_at')->get(),
        ]);
    }

    public function updateSetting(Request $request, PlatformSetting $setting)
    {
        $data = $request->validate(['value' => ['nullable', 'string'], 'reason' => ['required', 'string', 'max:500']]);
        $before = $setting->toArray();
        $setting->update(['value' => $data['value']]);
        $this->audit($request, 'setting.updated', $setting, $before, $data['reason']);
        return back()->with('success', 'Setting updated.');
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'in:month,year,one_time'], 'stripe_price_id' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $plan->toArray();
        $plan->update([
            'name' => $data['name'], 'price_cents' => (int) round($data['price'] * 100),
            'billing_interval' => $data['billing_interval'], 'stripe_price_id' => $data['stripe_price_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->audit($request, 'membership_plan.updated', $plan, $before, $data['reason']);
        return back()->with('success', 'Membership plan updated.');
    }

    public function updatePricing(Request $request, PricingRule $rule)
    {
        $data = $request->validate(['price' => ['required', 'numeric', 'min:0'], 'is_active' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $before = $rule->toArray();
        $rule->update(['price_cents' => (int) round($data['price'] * 100), 'is_active' => $request->boolean('is_active')]);
        $this->audit($request, 'pricing_rule.updated', $rule, $before, $data['reason']);
        return back()->with('success', 'Resource price updated.');
    }

    public function updateBenefit(Request $request, MembershipPlan $plan, Benefit $benefit)
    {
        $data = $request->validate(['value' => ['nullable', 'string', 'max:255'], 'reason' => ['required', 'string', 'max:500']]);
        $before = $plan->benefits()->whereKey($benefit->id)->first()?->pivot?->value;
        $plan->benefits()->syncWithoutDetaching([$benefit->id => ['value' => $data['value']]]);
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'membership_benefit.updated',
            'auditable_type' => MembershipPlan::class, 'auditable_id' => $plan->id,
            'before' => ['benefit' => $benefit->key, 'value' => $before],
            'after' => ['benefit' => $benefit->key, 'value' => $data['value']],
            'reason' => $data['reason'], 'ip_address' => $request->ip(),
        ]);
        return back()->with('success', 'Membership benefit updated.');
    }

    public function createHold(Request $request)
    {
        $data = $request->validate([
            'resource_id' => ['required', 'exists:resources,id'], 'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'], 'type' => ['required', 'in:admin_hold,maintenance'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $hold = AvailabilityBlock::create($data + ['created_by' => $request->user()->id]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'availability_hold.created', 'auditable_type' => AvailabilityBlock::class, 'auditable_id' => $hold->id, 'after' => $hold->toArray(), 'reason' => $data['reason'], 'ip_address' => $request->ip()]);
        return back()->with('success', 'Availability hold created.');
    }

    public function deleteHold(Request $request, AvailabilityBlock $hold)
    {
        $before = $hold->toArray();
        $hold->delete();
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'availability_hold.deleted', 'auditable_type' => AvailabilityBlock::class, 'auditable_id' => $hold->id, 'before' => $before, 'reason' => 'Removed by administrator', 'ip_address' => $request->ip()]);
        return back()->with('success', 'Availability hold removed.');
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

    public function customers(Request $request)
    {
        $query = User::with('memberships.plan', 'roles')->latest();
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($row) => $row->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($row) => $row->where('name', $request->string('role')));
        }
        return view('admin.customers', [
            'customers' => $query->paginate(50)->withQueryString(),
            'roles' => Role::orderBy('label')->get(),
        ]);
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
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'role.permissions_synced',
            'auditable_type' => Role::class, 'auditable_id' => $role->id,
            'before' => $before,
            'after' => ['label' => $role->fresh()->label, 'permissions' => $role->permissionRecords()->pluck('permissions.id')->all()],
            'reason' => $data['reason'], 'ip_address' => $request->ip(),
        ]);
        return back()->with('success', $role->label.' permissions synchronized.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate(['roles' => ['nullable', 'array'], 'roles.*' => ['integer', 'exists:roles,id'], 'reason' => ['required', 'string', 'max:500']]);
        $superAdminRoleId = Role::where('name', 'super_admin')->value('id');
        $keepsSuperAdmin = in_array($superAdminRoleId, array_map('intval', $data['roles'] ?? []), true);
        if ($user->hasRole('super_admin') && !$keepsSuperAdmin) {
            $otherSuperAdmins = User::whereKeyNot($user->id)->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))->exists();
            if ($user->is($request->user()) || !$otherSuperAdmins) {
                return back()->withErrors(['roles' => 'You cannot remove the active or final Super Admin role.']);
            }
        }
        $before = $user->roles()->pluck('roles.id')->all();
        $user->roles()->sync($data['roles'] ?? []);
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'user.roles_updated',
            'auditable_type' => User::class, 'auditable_id' => $user->id,
            'before' => ['role_ids' => $before], 'after' => ['role_ids' => $data['roles'] ?? []],
            'reason' => $data['reason'], 'ip_address' => $request->ip(),
        ]);
        return back()->with('success', 'Customer roles updated.');
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
                fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Status']);
                User::orderBy('id')->each(fn ($row) => fputcsv($out, [$row->id, $row->name, $row->email, $row->phone, $row->status]));
            } elseif ($type === 'leads') {
                fputcsv($out, ['ID', 'Type', 'Status', 'Name', 'Email', 'Phone', 'Created']);
                FormSubmission::orderBy('id')->each(fn ($row) => fputcsv($out, [$row->id, $row->type, $row->status, $row->name, $row->email, $row->phone, $row->created_at]));
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

    private function audit(Request $request, string $action, $model, array $before, ?string $reason): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'auditable_type' => $model::class, 'auditable_id' => $model->id,
            'before' => $before, 'after' => $model->fresh()->toArray(),
            'reason' => $reason, 'ip_address' => $request->ip(),
        ]);
    }
}
