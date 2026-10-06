<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Models\Benefit;
use App\Models\CustomerMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    use RecordsAudit;

    public const STATUSES = ['active' => 'Active', 'trialing' => 'Trialing', 'grace' => 'Grace period', 'past_due' => 'Past due', 'cancelled' => 'Cancelled', 'expired' => 'Expired'];
    public const INTERVALS = ['month' => 'Monthly', 'year' => 'Yearly', 'one_time' => 'One-time'];

    public function index(Request $request)
    {
        $plans = MembershipPlan::with('benefits')
            ->withCount(['memberships', 'memberships as active_memberships_count' => fn ($q) => $q->whereIn('status', ['active', 'trialing', 'grace'])])
            ->orderBy('sort_order')->get();

        $memberships = CustomerMembership::with(['user', 'plan'])->latest();
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $memberships->whereHas('user', fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }
        if ($request->filled('plan')) {
            $memberships->where('membership_plan_id', (int) $request->input('plan'));
        }
        if ($request->filled('status')) {
            $memberships->where('status', $request->string('status'));
        }

        $stats = [
            'active' => CustomerMembership::whereIn('status', ['active', 'trialing', 'grace'])->count(),
            'paid' => CustomerMembership::whereIn('status', ['active', 'trialing', 'grace'])->whereHas('plan', fn ($q) => $q->where('price_cents', '>', 0))->count(),
            'grace' => CustomerMembership::whereIn('status', ['grace', 'past_due'])->count(),
            'mrr_cents' => (int) CustomerMembership::where('status', 'active')->join('membership_plans', 'membership_plans.id', '=', 'customer_memberships.membership_plan_id')
                ->where('membership_plans.billing_interval', 'month')->sum('membership_plans.price_cents'),
        ];

        return view('admin.memberships', [
            'plans' => $plans,
            'benefits' => Benefit::orderBy('name')->get(),
            'memberships' => $memberships->paginate(25)->withQueryString(),
            'customers' => User::where('account_type', User::TYPE_CUSTOMER)->orderBy('name')->get(['id', 'name', 'email']),
            'stats' => $stats,
            'statuses' => self::STATUSES,
            'intervals' => self::INTERVALS,
            'tab' => $request->query('tab', 'plans'),
        ]);
    }

    public function storePlan(Request $request)
    {
        $data = $this->validatePlan($request);
        $plan = DB::transaction(function () use ($data, $request) {
            $plan = MembershipPlan::create([
                'slug' => Str::slug($data['slug'] ?? $data['name']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price_cents' => (int) round($data['price'] * 100),
                'billing_interval' => $data['billing_interval'],
                'stripe_price_id' => $data['stripe_price_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($data['sort_order'] ?? (MembershipPlan::max('sort_order') + 1)),
            ]);
            $plan->benefits()->sync($this->benefitPayload($data['benefits'] ?? []));
            return $plan;
        });
        $this->audit($request, 'membership_plan.created', $plan, null, $data['reason']);
        return redirect()->route('admin.memberships')->with('success', 'Membership plan "'.$plan->name.'" created.');
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $data = $this->validatePlan($request, $plan);
        $before = $plan->load('benefits')->toArray();
        DB::transaction(function () use ($data, $plan, $request) {
            $plan->update([
                'slug' => Str::slug($data['slug'] ?? $plan->slug),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price_cents' => (int) round($data['price'] * 100),
                'billing_interval' => $data['billing_interval'],
                'stripe_price_id' => $data['stripe_price_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($data['sort_order'] ?? $plan->sort_order),
            ]);
            $plan->benefits()->sync($this->benefitPayload($data['benefits'] ?? []));
        });
        $this->audit($request, 'membership_plan.updated', $plan, $before, $data['reason'], $plan->fresh('benefits')->toArray());
        return redirect()->route('admin.memberships')->with('success', 'Membership plan "'.$plan->name.'" updated.');
    }

    public function storeMembership(Request $request)
    {
        $data = $this->validateMembership($request);
        $existing = CustomerMembership::where('user_id', $data['user_id'])->where('membership_plan_id', $data['membership_plan_id'])->first();
        if ($existing) {
            return back()->withErrors(['user_id' => 'This customer already has a record for that plan. Edit the existing membership instead.'])->withInput()->with('modal', 'assign-membership');
        }
        $membership = CustomerMembership::create($this->membershipAttributes($data));
        $this->audit($request, 'customer_membership.created', $membership, null, $data['reason']);
        return redirect()->route('admin.memberships', ['tab' => 'members'])->with('success', 'Membership assigned.');
    }

    public function updateMembership(Request $request, CustomerMembership $membership)
    {
        $data = $this->validateMembership($request, $membership);
        $before = $membership->toArray();
        $membership->update($this->membershipAttributes($data));
        $this->audit($request, 'customer_membership.updated', $membership, $before, $data['reason']);
        return redirect()->route('admin.memberships', ['tab' => 'members'])->with('success', 'Membership updated.');
    }

    private function validatePlan(Request $request, ?MembershipPlan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('membership_plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', Rule::in(array_keys(self::INTERVALS))],
            'stripe_price_id' => ['nullable', 'string', 'max:190'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }

    private function validateMembership(Request $request, ?CustomerMembership $membership = null): array
    {
        return $request->validate([
            'user_id' => [$membership ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'stripe_customer_id' => ['nullable', 'string', 'max:190'],
            'stripe_subscription_id' => ['nullable', 'string', 'max:190', Rule::unique('customer_memberships', 'stripe_subscription_id')->ignore($membership?->id)],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:current_period_start'],
            'grace_ends_at' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }

    private function membershipAttributes(array $data): array
    {
        $attributes = [
            'membership_plan_id' => $data['membership_plan_id'],
            'status' => $data['status'],
            'stripe_customer_id' => $data['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
            'current_period_start' => $data['current_period_start'] ?? null,
            'current_period_end' => $data['current_period_end'] ?? null,
            'grace_ends_at' => $data['grace_ends_at'] ?? null,
            'cancelled_at' => $data['status'] === 'cancelled' ? ($data['cancelled_at'] ?? now()) : ($data['cancelled_at'] ?? null),
        ];
        if (!empty($data['user_id'])) {
            $attributes['user_id'] = $data['user_id'];
        }
        return $attributes;
    }

    private function benefitPayload(array $values): array
    {
        $payload = [];
        foreach ($values as $benefitId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $payload[(int) $benefitId] = ['value' => $value];
        }
        return $payload;
    }
}
