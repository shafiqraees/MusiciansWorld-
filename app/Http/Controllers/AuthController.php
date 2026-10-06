<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function registerForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'marketing' => ['nullable', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                ...$data,
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'communication_preferences' => ['marketing' => (bool) ($data['marketing'] ?? false)],
            ]);
            $user->roles()->attach(Role::where('name', 'customer')->value('id'));
            $user->memberships()->create([
                'membership_plan_id' => MembershipPlan::where('slug', 'community')->value('id'),
                'status' => 'active',
            ]);
            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('account.dashboard')->with('success', 'Welcome to MusiciansWorld. Your free account is ready.');
    }

    public function loginForm()
    {
        return view('auth.login');
    }

    /** Customer sign-in (public website). */
    public function login(Request $request)
    {
        return $this->attemptLogin($request, portal: false);
    }

    public function adminLoginForm()
    {
        return view('auth.admin-login');
    }

    /** Internal admin / staff sign-in (operations portal). */
    public function adminLogin(Request $request)
    {
        return $this->attemptLogin($request, portal: true);
    }

    private function attemptLogin(Request $request, bool $portal)
    {
        $credentials = $request->validate(['email' => ['required', 'string'], 'password' => ['required', 'string']]);
        $email = strtolower(trim($credentials['email']));
        if ($portal && $email === 'admin') {
            $email = 'admin@musiciansworld.com';
        }
        $throttleKey = ($portal ? 'admin-login:' : 'login:').$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            return back()->withErrors(['email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.'])->onlyInput('email');
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::with('roles')->where('email', $email)->first() : null;
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 15 * 60);
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }
        if ($user->status !== 'active') {
            RateLimiter::hit($throttleKey, 15 * 60);
            return back()->withErrors(['email' => 'This account is '.$user->status.'. Contact MusiciansWorld support for help.'])->onlyInput('email');
        }
        if ($portal && !$user->isStaffAccount()) {
            RateLimiter::hit($throttleKey, 15 * 60);
            return back()->withErrors(['email' => 'This account does not have access to the operations portal. Use the customer sign-in instead.'])->onlyInput('email');
        }
        if (!$portal && $user->isStaffAccount()) {
            return back()->withErrors(['email' => 'This is an internal team account. Please sign in through the Admin Login.'])->onlyInput('email')->with('admin_login_hint', true);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        return redirect()->intended($portal ? route('admin.dashboard') : route('account.dashboard'));
    }

    public function logout(Request $request)
    {
        $wasPortal = $request->is('admin/*') || $request->routeIs('admin.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return $wasPortal ? redirect()->route('admin.login')->with('success', 'You have been signed out.') : redirect('/');
    }

    public function forgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = PasswordBroker::sendResetLink($request->only('email'));
        return $status === PasswordBroker::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $status = PasswordBroker::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function verify(Request $request)
    {
        if (!$request->user()->hasVerifiedEmail()) {
            $request->user()->markEmailAsVerified();
        }
        return redirect()->route('account.dashboard')->with('success', 'Email address verified.');
    }

    public function resendVerification(Request $request)
    {
        if (!$request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }
        return back()->with('success', 'Verification email sent.');
    }
}
