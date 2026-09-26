<?php

namespace App\Http\Controllers;

use App\Models\OrganizerProfile;
use App\Models\PlatformSetting;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Throwable;

class ConnectController extends Controller
{
    public function start(Request $request, StripeService $stripe)
    {
        abort_unless(PlatformSetting::valueOf('stripe.connect.enabled', false), 503, 'Stripe Connect onboarding is awaiting MWCN architecture approval.');
        $profile = OrganizerProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['display_name' => $request->user()->name, 'status' => 'eligible']
        );
        try {
            if (!$profile->stripe_account_id) {
                $account = $stripe->client()->accounts->create([
                    'type' => 'express',
                    'country' => 'US',
                    'email' => $request->user()->email,
                    'capabilities' => ['transfers' => ['requested' => true]],
                    'metadata' => ['mwcn_user_id' => $request->user()->id],
                ]);
                $profile->update(['stripe_account_id' => $account->id]);
            }
            $link = $stripe->client()->accountLinks->create([
                'account' => $profile->stripe_account_id,
                'refresh_url' => route('connect.start'),
                'return_url' => route('connect.return'),
                'type' => 'account_onboarding',
            ]);
            return redirect()->away($link->url);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['connect' => 'Stripe onboarding could not be started.']);
        }
    }

    public function returned(Request $request, StripeService $stripe)
    {
        $profile = $request->user()->organizerProfile;
        if ($profile?->stripe_account_id) {
            try {
                $account = $stripe->client()->accounts->retrieve($profile->stripe_account_id);
                $profile->update([
                    'stripe_charges_enabled' => (bool) $account->charges_enabled,
                    'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
                    'requirements' => $account->requirements?->toArray(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        return redirect()->route('account.dashboard')->with('success', 'Stripe organizer onboarding status refreshed.');
    }
}
