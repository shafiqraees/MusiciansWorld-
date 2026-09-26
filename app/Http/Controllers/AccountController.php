<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user()->load([
            'memberships.plan.benefits',
            'bookings.resource',
            'registrations.program',
            'tickets.event',
            'events.settlement',
            'transactions',
        ]);
        return view('account.dashboard', compact('user'));
    }

    public function profile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'marketing' => ['nullable', 'boolean'],
        ]);
        $request->user()->update([
            ...$data,
            'communication_preferences' => ['marketing' => $request->boolean('marketing')],
        ]);
        return back()->with('success', 'Profile updated.');
    }
}
