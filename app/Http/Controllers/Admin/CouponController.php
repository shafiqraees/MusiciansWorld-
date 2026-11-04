<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\ClientPricing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    use RecordsAudit;

    public function index(ClientPricing $pricing)
    {
        return view('admin.coupons', [
            'coupons' => Coupon::query()->with('assignedUser')->withCount([
                'redemptions as used_count' => fn ($query) => $query->where('status', 'used'),
                'redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved'),
            ])->latest()->get(),
            'redemptions' => CouponRedemption::query()->with(['coupon', 'booking', 'user'])->latest()->limit(100)->get(),
            'services' => ClientPricing::SERVICES,
            'percents' => ClientPricing::PERCENTS,
            'standard' => number_format($pricing->standardHourlyCents() / 100, 2, '.', ''),
            'firstTime' => number_format($pricing->firstTimeHourlyCents() / 100, 2, '.', ''),
        ]);
    }

    public function updateRates(Request $request)
    {
        $data = $request->validate([
            'standard' => ['required', 'numeric', 'min:1', 'max:1000'],
            'first_time' => ['required', 'numeric', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $standard = $this->saveRate('studio_standard_hourly_cents', (int) round($data['standard'] * 100));
        $this->saveRate('studio_first_time_hourly_cents', (int) round($data['first_time'] * 100));
        $this->audit($request, 'pricing.rates_updated', $standard, null, $data['reason']);

        return back()->with('success', 'Studio hourly rates saved.');
    }

    public function store(Request $request)
    {
        $coupon = Coupon::query()->create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->audit($request, 'coupon.created', $coupon, null, $request->string('reason')->toString());

        return back()->with('success', 'Code '.$coupon->code.' created.');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $before = $coupon->toArray();
        $coupon->update($this->validated($request, $coupon));
        $this->audit($request, 'coupon.updated', $coupon, $before, $request->string('reason')->toString());

        return back()->with('success', 'Code '.$coupon->code.' updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('coupons', 'code')->ignore($coupon)],
            'kind' => ['required', Rule::in(['coupon', 'family'])],
            'percent' => ['required', 'integer', Rule::in(ClientPricing::PERCENTS)],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:100000'],
            'once_per_customer' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'assigned_email' => ['nullable', 'email', 'max:190', 'required_if:kind,family'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', Rule::in(array_keys(ClientPricing::SERVICES))],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $email = isset($data['assigned_email']) ? strtolower($data['assigned_email']) : null;
        $maxUses = (int) $data['max_uses'];

        return [
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'kind' => $data['kind'],
            'percent' => (int) $data['percent'],
            'expires_at' => $data['expires_at'] ?? null,
            'max_uses' => $maxUses,
            'once_per_customer' => $maxUses === 1 || $request->boolean('once_per_customer'),
            'is_active' => $request->boolean('is_active'),
            'assigned_email' => $email,
            'assigned_user_id' => $email
                ? User::query()->whereRaw('LOWER(email) = ?', [$email])->value('id')
                : null,
            'services' => array_values($data['services']),
        ];
    }

    private function saveRate(string $key, int $cents): PlatformSetting
    {
        return PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => 'booking', 'type' => 'integer', 'value' => (string) $cents, 'is_public' => false]
        );
    }
}
