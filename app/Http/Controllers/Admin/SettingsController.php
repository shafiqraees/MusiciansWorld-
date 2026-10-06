<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\IntegrationJob;
use App\Models\PlatformSetting;
use App\Models\PricingRule;
use App\Models\Resource;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use RecordsAudit;

    public const SECTIONS = [
        'platform' => ['label' => 'Platform settings', 'icon' => 'settings', 'intro' => 'Fees, booking rules and feature switches that control how the platform behaves.'],
        'pricing' => ['label' => 'Spaces & pricing', 'icon' => 'tag', 'intro' => 'Studio A and SoundTunnel resources with their published duration-based price list.'],
        'availability' => ['label' => 'Availability holds', 'icon' => 'calendar', 'intro' => 'Block out maintenance windows or administrative holds so customers cannot book those times.'],
        'integrations' => ['label' => 'Integrations', 'icon' => 'plug', 'intro' => 'Connection status for Stripe, email delivery and Microsoft 365 calendars.'],
    ];

    public const GROUP_LABELS = [
        'fees' => ['label' => 'Platform fees', 'intro' => 'Promoter fees are expressed in basis points (100 bps = 1%).'],
        'booking' => ['label' => 'Booking rules', 'intro' => 'Controls for checkout holds and reservation behaviour.'],
        'stripe' => ['label' => 'Stripe features', 'intro' => 'Feature switches for Stripe Connect onboarding and organizer transfers.'],
    ];

    public function index()
    {
        return view('admin.settings.index', [
            'sections' => self::SECTIONS,
            'counts' => [
                'platform' => PlatformSetting::count(),
                'pricing' => PricingRule::count(),
                'availability' => AvailabilityBlock::where('ends_at', '>', now())->count(),
                'integrations' => collect($this->integrationStatus())->where('connected', true)->count().' / '.count($this->integrationStatus()),
            ],
        ]);
    }

    public function platform()
    {
        return view('admin.settings.platform', [
            'groups' => PlatformSetting::orderBy('group')->orderBy('key')->get()->groupBy('group'),
            'groupLabels' => self::GROUP_LABELS,
            'types' => ['string' => 'Text', 'integer' => 'Whole number', 'boolean' => 'On / off', 'json' => 'JSON'],
        ]);
    }

    public function storeSetting(Request $request)
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_\-]+$/'],
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9_.\-]+$/', 'unique:platform_settings,key'],
            'type' => ['required', Rule::in(['string', 'integer', 'boolean', 'json'])],
            'value' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $setting = PlatformSetting::create([
            'group' => $data['group'], 'key' => $data['key'], 'type' => $data['type'],
            'value' => $this->normaliseValue($data['type'], $data['value'] ?? null),
            'is_public' => $request->boolean('is_public'),
        ]);
        $this->audit($request, 'setting.created', $setting, null, $data['reason']);
        return back()->with('success', 'Setting "'.$setting->key.'" created.');
    }

    public function updateSetting(Request $request, PlatformSetting $setting)
    {
        $data = $request->validate([
            'value' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in(['string', 'integer', 'boolean', 'json'])],
            'is_public' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $type = $data['type'] ?? $setting->type;
        if ($type === 'json' && $data['value'] !== null && json_decode($data['value']) === null && json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['value' => 'The value must be valid JSON.'])->with('modal', 'setting-'.$setting->id);
        }
        $before = $setting->toArray();
        $setting->update(['value' => $this->normaliseValue($type, $data['value'] ?? null), 'type' => $type, 'is_public' => $request->boolean('is_public')]);
        $this->audit($request, 'setting.updated', $setting, $before, $data['reason']);
        return back()->with('success', 'Setting "'.$setting->key.'" updated.');
    }

    public function pricing()
    {
        return view('admin.settings.pricing', [
            'resources' => Resource::with(['pricingRules' => fn ($q) => $q->orderBy('duration_minutes')])->orderBy('name')->get(),
            'serviceTypes' => ['studio_booking' => 'Studio booking', 'venue_rental' => 'Venue rental', 'lesson' => 'Lesson', 'add_on' => 'Add-on'],
        ]);
    }

    public function updateResource(Request $request, Resource $resource)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'timezone:all'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'calendar_id' => ['nullable', 'string', 'max:190'],
            'is_active' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $resource->toArray();
        $resource->update(['name' => $data['name'], 'type' => $data['type'], 'timezone' => $data['timezone'], 'capacity' => $data['capacity'] ?? null, 'calendar_id' => $data['calendar_id'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $this->audit($request, 'resource.updated', $resource, $before, $data['reason']);
        return back()->with('success', $resource->name.' updated.');
    }

    public function storePricing(Request $request)
    {
        $data = $this->validatePricing($request);
        $rule = PricingRule::create([
            'resource_id' => $data['resource_id'], 'service_type' => $data['service_type'], 'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'] ?? null, 'price_cents' => (int) round($data['price'] * 100),
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->audit($request, 'pricing_rule.created', $rule, null, $data['reason']);
        return back()->with('success', 'Price "'.$rule->name.'" added.');
    }

    public function updatePricing(Request $request, PricingRule $rule)
    {
        $data = $this->validatePricing($request, $rule);
        $before = $rule->toArray();
        $rule->update([
            'resource_id' => $data['resource_id'], 'service_type' => $data['service_type'], 'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'] ?? null, 'price_cents' => (int) round($data['price'] * 100),
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->audit($request, 'pricing_rule.updated', $rule, $before, $data['reason']);
        return back()->with('success', 'Price "'.$rule->name.'" updated.');
    }

    public function availability(Request $request)
    {
        $showPast = $request->boolean('past');
        $holds = AvailabilityBlock::with('resource')->when(!$showPast, fn ($q) => $q->where('ends_at', '>', now()))->orderBy($showPast ? 'ends_at' : 'starts_at', $showPast ? 'desc' : 'asc');
        return view('admin.settings.availability', [
            'holds' => $holds->paginate(25)->withQueryString(),
            'resources' => Resource::orderBy('name')->get(),
            'showPast' => $showPast,
            'types' => ['admin_hold' => 'Administrative hold', 'maintenance' => 'Maintenance'],
        ]);
    }

    public function createHold(Request $request)
    {
        $data = $request->validate([
            'resource_id' => ['required', 'exists:resources,id'], 'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'], 'type' => ['required', 'in:admin_hold,maintenance'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $hold = AvailabilityBlock::create($data + ['created_by' => $request->user()->id]);
        $this->audit($request, 'availability_hold.created', $hold, null, $data['reason']);
        return back()->with('success', 'Availability hold created.');
    }

    public function deleteHold(Request $request, AvailabilityBlock $hold)
    {
        $before = $hold->toArray();
        $hold->delete();
        $this->audit($request, 'availability_hold.deleted', $hold, $before, 'Removed by administrator', []);
        return back()->with('success', 'Availability hold removed.');
    }

    public function integrations()
    {
        return view('admin.settings.integrations', [
            'integrations' => $this->integrationStatus(),
            'failedJobs' => IntegrationJob::where('status', 'failed')->count(),
            'recentWebhooks' => WebhookEvent::latest()->limit(8)->get(),
        ]);
    }

    private function integrationStatus(): array
    {
        $stripeSecret = (string) config('services.stripe.secret');
        return [
            ['name' => 'Stripe payments', 'connected' => $stripeSecret !== '', 'mode' => str_starts_with($stripeSecret, 'sk_live') ? 'Live mode' : ($stripeSecret ? 'Test mode' : 'Not configured'),
                'detail' => config('services.stripe.webhook_secret') ? 'Webhook signing secret present' : 'Webhook signing secret missing', 'env' => ['STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET']],
            ['name' => 'Email delivery', 'connected' => config('mail.default') === 'smtp' && config('mail.mailers.smtp.host'), 'mode' => ucfirst((string) config('mail.default')),
                'detail' => (string) config('mail.mailers.smtp.host').' · from '.config('mail.from.address'), 'env' => ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_FROM_ADDRESS']],
            ['name' => 'Microsoft 365 calendars', 'connected' => (bool) config('services.microsoft.client_id'), 'mode' => config('services.microsoft.client_id') ? 'Entra app registered' : 'Not configured',
                'detail' => config('services.microsoft.studio_calendar') ? 'Studio A calendar mapped' : 'Calendar mailboxes not mapped', 'env' => ['MICROSOFT_TENANT_ID', 'MICROSOFT_CLIENT_ID', 'MICROSOFT_CLIENT_SECRET']],
            ['name' => 'Background queue', 'connected' => !IntegrationJob::where('status', 'failed')->exists(), 'mode' => ucfirst((string) config('queue.default')).' driver',
                'detail' => IntegrationJob::where('status', 'failed')->count().' failed automation jobs', 'env' => ['QUEUE_CONNECTION']],
        ];
    }

    private function validatePricing(Request $request, ?PricingRule $rule = null): array
    {
        return $request->validate([
            'resource_id' => ['required', 'exists:resources,id'],
            'service_type' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:150'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }

    private function normaliseValue(string $type, ?string $value): ?string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer' => $value === null || $value === '' ? null : (string) (int) $value,
            default => $value,
        };
    }
}
