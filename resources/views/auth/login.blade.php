<?php page_start('Customer sign in — MusiciansWorld', 'Sign in to your connected MWCN customer account.'); ?>
<section class="section"><div class="wrap max-w-xl">
<p class="kicker">Customer login</p>
<h1 class="display mt-3 text-5xl">Sign in to your account</h1>
<p class="mt-4 text-dim">One MWCN account for memberships, Studio A bookings, DJ School, events and tickets.</p>
@if(session('success'))<div class="mt-6 border border-green-500/40 p-4 text-green-300">{{ session('success') }}</div>@endif
@if($errors->any())
  <div class="mt-6 border border-red-500/50 p-4 text-red-300">
    {{ $errors->first() }}
    @if(session('admin_login_hint'))<p class="mt-2"><a class="text-mark underline" href="{{ route('admin.login') }}">Go to the Admin Login →</a></p>@endif
  </div>
@endif
<form method="post" action="{{ url('/login') }}" class="login-form mt-8 grid gap-5">@csrf
<label>Email address<input class="login-input mt-2 w-full" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus placeholder="you@email.com"></label>
<label>Password
  <span class="login-password mt-2">
    <input class="login-input w-full" id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Your password">
    <button class="login-eye" type="button" data-reveal-password aria-controls="login-password" aria-pressed="false" aria-label="Show password">
      <svg data-eye-open viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
      <svg data-eye-shut class="is-hidden" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-3.2 4.2"/><path d="M6.1 6.1C3.8 7.8 2 12 2 12s3.5 7 10 7a10.6 10.6 0 0 0 4.1-.8"/></svg>
    </button>
  </span>
</label>
<div class="flex flex-wrap items-center justify-between gap-3 text-sm">
  <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
  <a class="text-mark" href="{{ route('password.request') }}">Forgot your password?</a>
</div>
<button class="btn btn-mark" type="submit">Sign in</button></form>
<p class="mt-6 text-dim">New to MWCN? <a class="text-mark" href="{{ route('register') }}">Create your free account</a>.</p>
<p class="mt-3 text-xs text-dim">MusiciansWorld team member? <a class="text-mark" href="{{ route('admin.login') }}">Use the Admin Login</a>.</p>
</div></section><?php page_end(); ?>
