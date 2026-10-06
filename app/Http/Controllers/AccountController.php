<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CustomerMembership;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\StripeService;
use App\Support\Finance;
use Illuminate\Http\Request;
use Throwable;

/**
 * Customer portal. One page per module, all sharing the account sidebar.
 */
class AccountController extends Controller
{
    /* ----------------------------------------------------------------- pages */

    public function dashboard(Request $request)
    {
        $user = $request->user()->load(['roles', 'memberships.plan', 'bookings.resource', 'registrations.program', 'tickets.event.resource', 'tickets.ticketType', 'events.ticketTypes', 'events.settlement', 'transactions', 'organizerProfile']);
        $membership = $this->activeMembership($user);
        $upcomingBookings = $user->bookings->filter(fn ($b) => $b->ends_at->isFuture() && !in_array($b->status, ['cancelled', 'refunded', 'failed'], true))->sortBy('starts_at');
        $upcomingTickets = $user->tickets->filter(fn ($t) => $t->event?->ends_at?->isFuture())->sortBy(fn ($t) => $t->event->starts_at);
        $receipts = $user->transactions->filter(fn ($t) => Finance::isCustomerFacing($t->type) && $t->status === 'succeeded');

        return view('account.overview', [
            'user' => $user,
            'membership' => $membership,
            'benefits' => $this->recognizedBenefits($membership),
            'upcomingBookings' => $upcomingBookings,
            'upcomingTickets' => $upcomingTickets,
            'recentReceipts' => $user->transactions->filter(fn ($t) => Finance::isCustomerFacing($t->type))->sortByDesc('created_at')->take(5),
            'spent' => (int) $receipts->sum('amount_cents'),
            'activeEvents' => $user->events->whereIn('status', ['submitted', 'approved', 'published', 'changes_requested'])->count(),
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load('roles');

        return view('account.profile', ['user' => $user, 'prefs' => $user->communication_preferences ?? []]);
    }

    public function membership(Request $request)
    {
        $user = $request->user()->load('memberships.plan.benefits');
        $membership = $this->activeMembership($user);

        return view('account.membership', [
            'user' => $user,
            'membership' => $membership,
            'benefits' => $this->recognizedBenefits($membership),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'history' => $user->memberships->sortByDesc('id'),
        ]);
    }

    public function benefits(Request $request)
    {
        $user = $request->user()->load('memberships.plan.benefits');
        $membership = $this->activeMembership($user);
        $plans = MembershipPlan::with('benefits')->where('is_active', true)->orderBy('sort_order')->get();

        return view('account.benefits', [
            'user' => $user,
            'membership' => $membership,
            'benefits' => $this->recognizedBenefits($membership),
            'plans' => $plans,
        ]);
    }

    public function bookings(Request $request)
    {
        $user = $request->user()->load('bookings.resource', 'bookings.transactions');
        $upcoming = $user->bookings->filter(fn ($b) => $b->ends_at->isFuture() && !in_array($b->status, ['cancelled', 'refunded', 'failed'], true))->sortBy('starts_at');
        $past = $user->bookings->reject(fn ($b) => $upcoming->contains($b))->sortByDesc('starts_at');

        return view('account.bookings', [
            'user' => $user,
            'upcoming' => $upcoming,
            'past' => $past,
            'totalSpent' => (int) $user->bookings->where('payment_status', 'paid')->sum('total_cents'),
        ]);
    }

    public function school(Request $request)
    {
        $user = $request->user()->load('registrations.program', 'registrations.instructor', 'submissions');

        return view('account.school', [
            'user' => $user,
            'registrations' => $user->registrations->sortByDesc('created_at'),
            'inquiries' => $user->submissions->whereIn('type', ['dj_school_inquiry', 'dj_enrollment', 'dj_lesson_inquiry'])->sortByDesc('created_at'),
        ]);
    }

    public function tickets(Request $request)
    {
        $user = $request->user()->load('tickets.event.resource', 'tickets.ticketType', 'tickets.order.event', 'tickets.order.items.ticketType', 'tickets.order.transactions');
        $upcoming = $user->tickets->filter(fn ($t) => $t->event?->ends_at?->isFuture())->sortBy(fn ($t) => $t->event->starts_at);
        $past = $user->tickets->reject(fn ($t) => $upcoming->contains($t))->sortByDesc(fn ($t) => $t->event?->starts_at);
        $orders = $user->tickets->pluck('order')->filter()->unique('id')->sortByDesc('created_at');

        return view('account.tickets', [
            'user' => $user,
            'upcoming' => $upcoming,
            'past' => $past,
            'orders' => $orders,
        ]);
    }

    public function events(Request $request)
    {
        $user = $request->user()->load(['organizerProfile', 'events.ticketTypes', 'events.settlement', 'events.resource', 'events.orders']);
        $filter = $request->string('status')->toString();
        $groups = [
            '' => 'All',
            'draft' => 'Drafts',
            'pending' => 'Submitted',
            'published' => 'Published',
            'completed' => 'Completed',
        ];
        $events = $user->events->sortByDesc('starts_at')->filter(fn (Event $event) => match ($filter) {
            'draft' => in_array($event->status, ['draft', 'changes_requested', 'rejected'], true),
            'pending' => $event->status === 'submitted',
            'published' => in_array($event->status, ['approved', 'published', 'suspended'], true),
            'completed' => in_array($event->status, ['completed', 'closeout', 'paid_out'], true),
            default => true,
        });
        $statements = $user->events->mapWithKeys(fn (Event $event) => [$event->id => Finance::eventStatement($event)]);
        $totals = [
            'gross' => (int) $statements->sum('gross'),
            'platform_fee' => (int) $statements->sum('platform_fee'),
            'organizer' => (int) $statements->sum('organizer'),
            'tickets' => (int) $statements->sum('tickets_sold'),
            'paid_out' => (int) $statements->filter(fn ($s) => $s['payout_status'] === 'paid')->sum('organizer'),
        ];

        return view('account.events', compact('user', 'events', 'groups', 'filter', 'statements', 'totals'));
    }

    public function eventDetail(Request $request, Event $event)
    {
        abort_unless($event->organizer_id === $request->user()->id, 403);
        $event->load(['ticketTypes', 'settlement', 'resource', 'services', 'orders.items.ticketType', 'orders.tickets', 'orders.transactions']);
        $statement = Finance::eventStatement($event);
        $tickets = $event->orders->flatMap->tickets;
        $ledger = FinancialTransaction::where('transactable_type', Order::class)
            ->whereIn('transactable_id', $event->orders->pluck('id'))
            ->latest()->get()
            ->concat($event->settlement ? FinancialTransaction::where('transactable_type', $event->settlement::class)->where('transactable_id', $event->settlement->id)->get() : collect())
            ->sortByDesc('created_at');

        return view('account.event-detail', [
            'user' => $request->user(),
            'event' => $event,
            'statement' => $statement,
            'orders' => $event->orders->sortByDesc('created_at'),
            'checkedIn' => $tickets->whereIn('status', ['checked_in', 'used'])->count(),
            'issued' => $tickets->whereNotIn('status', ['cancelled', 'voided', 'refunded'])->count(),
            'ledger' => $ledger,
        ]);
    }

    public function payments(Request $request)
    {
        $user = $request->user()->load('transactions');
        $filter = $request->string('type')->toString();
        $all = $user->transactions->filter(fn ($t) => Finance::isCustomerFacing($t->type))->sortByDesc('created_at')->values();
        $filtered = match ($filter) {
            'tickets' => $all->where('type', 'gross_ticket_revenue'),
            'bookings' => $all->whereIn('type', ['studio_booking', 'booking_payment', 'soundtunnel_booking']),
            'memberships' => $all->whereIn('type', ['membership', 'membership_payment']),
            'refunds' => $all->where('type', 'refund'),
            default => $all,
        };
        $filtered->loadMorph('transactable', [
            Order::class => ['event', 'items.ticketType', 'transactions'],
            Booking::class => ['resource'],
            CustomerMembership::class => ['plan'],
        ]);
        $succeeded = $all->where('status', 'succeeded');

        return view('account.payments', [
            'user' => $user,
            'transactions' => $filtered->values(),
            'filter' => $filter,
            'totals' => [
                'paid' => (int) $succeeded->where('amount_cents', '>', 0)->sum('amount_cents'),
                'refunded' => (int) abs($succeeded->where('type', 'refund')->sum('amount_cents')),
                'count' => $all->count(),
                'last' => $all->first()?->created_at,
            ],
        ]);
    }

    public function receipt(Request $request, FinancialTransaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        abort_if(!Finance::isCustomerFacing($transaction->type), 404);
        $transaction->loadMorph('transactable', [
            Booking::class => ['resource'],
            Order::class => ['event', 'items.ticketType', 'transactions'],
            CustomerMembership::class => ['plan'],
        ]);
        $breakdown = $transaction->transactable instanceof Order ? Finance::orderBreakdown($transaction->transactable) : null;

        return view('account.receipt', compact('transaction', 'breakdown'));
    }

    /* --------------------------------------------------------------- actions */

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'marketing' => ['nullable', 'boolean'],
            'booking_updates' => ['nullable', 'boolean'],
            'event_updates' => ['nullable', 'boolean'],
            'school_updates' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'communication_preferences' => array_merge($request->user()->communication_preferences ?? [], [
                'marketing' => $request->boolean('marketing'),
                'booking_updates' => $request->boolean('booking_updates'),
                'event_updates' => $request->boolean('event_updates'),
                'school_updates' => $request->boolean('school_updates'),
            ]),
        ]);

        return redirect()->route('account.profile')->with('success', 'Profile updated.');
    }

    public function cancelMembership(Request $request)
    {
        $request->validate(['confirm' => ['accepted']]);
        $membership = $this->cancellableMembership($request->user());
        if (!$membership) {
            return back()->withErrors(['membership' => 'Free Community stays with your account. Paid plans can be set to end at the close of the current period.']);
        }
        if ($membership->cancelled_at) {
            return redirect()->route('account.membership')->with('success', 'This membership is already scheduled to end.');
        }
        if (!$this->scheduleStripeCancellation($membership, true)) {
            return back()->withErrors(['membership' => 'The billing provider could not schedule this cancellation. Your membership is unchanged.']);
        }

        $before = $membership->only(['status', 'cancelled_at']);
        $endsLater = $membership->current_period_end && $membership->current_period_end->isFuture();
        $membership->update($endsLater ? ['cancelled_at' => now()] : ['status' => 'cancelled', 'cancelled_at' => now()]);
        if (!$endsLater) {
            $this->ensureFreeCommunity($request->user());
        }
        $this->audit($request, 'membership.cancellation_scheduled', $membership, $before, $membership->fresh()->only(['status', 'cancelled_at']));

        $message = $endsLater
            ? 'Your '.$membership->plan->name.' stays active through '.$membership->current_period_end->format('M j, Y').'. Your account and history remain.'
            : 'Your paid membership has ended. Your free MWCN account and history remain.';

        return redirect()->route('account.membership')->with('success', $message);
    }

    public function resumeMembership(Request $request)
    {
        $membership = $request->user()->memberships()
            ->whereIn('status', ['active', 'trialing', 'grace'])
            ->whereNotNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            ->latest()->first();
        if (!$membership) {
            return back()->withErrors(['membership' => 'There is no scheduled cancellation to keep.']);
        }
        if (!$this->scheduleStripeCancellation($membership, false)) {
            return back()->withErrors(['membership' => 'The billing provider could not restore this membership. It is still scheduled to end.']);
        }
        $before = $membership->only(['status', 'cancelled_at']);
        $membership->update(['cancelled_at' => null]);
        $this->audit($request, 'membership.cancellation_withdrawn', $membership, $before, $membership->fresh()->only(['status', 'cancelled_at']));

        return redirect()->route('account.membership')->with('success', 'Your membership will continue to renew.');
    }

    public function requestBookingChange(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless($booking->status === 'confirmed' && $booking->ends_at->isFuture(), 422, 'This booking can no longer be changed from your account.');
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        $meta = $booking->meta ?? [];
        $meta['change_requests'][] = ['at' => now()->toIso8601String(), 'message' => $data['message']];
        $stamp = now()->timezone($booking->resource->timezone ?? config('app.timezone'))->format('M j, Y g:i A');
        $booking->update([
            'notes' => trim(($booking->notes ? $booking->notes."\n\n" : '').'[Customer change request '.$stamp.'] '.$data['message']),
            'meta' => $meta,
        ]);
        $this->audit($request, 'booking.change_requested', $booking, null, ['message' => $data['message']]);

        return redirect()->route('account.bookings')->with('success', 'Change request sent for '.$booking->reference.'. The booking stays confirmed until MWCN updates it.');
    }

    /* --------------------------------------------------------------- helpers */

    private function activeMembership(User $user): ?CustomerMembership
    {
        return $user->memberships
            ->whereIn('status', ['active', 'trialing', 'grace'])
            ->sortByDesc(fn (CustomerMembership $row) => [(int) ($row->plan?->price_cents ?? 0), $row->id])
            ->first();
    }

    private function cancellableMembership(User $user): ?CustomerMembership
    {
        return $user->memberships()->with('plan')
            ->whereIn('status', ['active', 'trialing', 'grace'])
            ->whereHas('plan', fn ($q) => $q->where('price_cents', '>', 0))
            ->latest()->first();
    }

    private function ensureFreeCommunity(User $user): void
    {
        $planId = MembershipPlan::where('slug', 'community')->value('id');
        if (!$planId) {
            return;
        }
        $existing = $user->memberships()->where('membership_plan_id', $planId)->latest()->first();
        if ($existing && in_array($existing->status, ['active', 'trialing', 'grace'], true)) {
            return;
        }
        if ($existing) {
            $existing->update(['status' => 'active', 'cancelled_at' => null]);
            return;
        }
        $user->memberships()->create(['membership_plan_id' => $planId, 'status' => 'active']);
    }

    private function scheduleStripeCancellation(CustomerMembership $membership, bool $cancel): bool
    {
        if (!$membership->stripe_subscription_id || !config('services.stripe.secret')) {
            return true;
        }
        try {
            app(StripeService::class)->client()->subscriptions->update($membership->stripe_subscription_id, ['cancel_at_period_end' => $cancel]);
        } catch (Throwable $exception) {
            if (!str_contains($exception->getMessage(), 'No such subscription')) {
                report($exception);
                return false;
            }
        }

        return true;
    }

    private function recognizedBenefits(?CustomerMembership $membership): array
    {
        if (!$membership?->plan) {
            return [];
        }
        $plan = $membership->plan->relationLoaded('benefits') ? $membership->plan : $membership->plan->load('benefits');
        $now = now();
        $lines = [];
        foreach ($plan->benefits as $benefit) {
            if (($benefit->pivot->effective_from && $now->lt($benefit->pivot->effective_from)) || ($benefit->pivot->effective_until && $now->gt($benefit->pivot->effective_until))) {
                continue;
            }
            $value = $benefit->pivot->value;
            if ($value === null || $value === '') {
                continue;
            }
            $lines[] = [
                'name' => $benefit->name,
                'key' => $benefit->key,
                'value' => $value,
                'detail' => match ($benefit->key) {
                    'promoter_fee_bps' => 'MWCN keeps '.Finance::percent((int) $value).' of paid ticket sales on events you host.',
                    'studio_discount_percent' => ((int) $value).'% discount recognized on eligible Studio A bookings.',
                    'included_studio_minutes' => number_format((int) $value).' included Studio A minutes per month.',
                    default => $benefit->type === 'boolean' ? ($benefit->description ?: $benefit->name) : trim($benefit->name.': '.$value),
                },
            ];
        }

        return $lines;
    }

    private function audit(Request $request, string $action, $model, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);
    }
}
