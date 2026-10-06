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
<form method="post" action="{{ url('/login') }}" class="mt-8 grid gap-5">@csrf
<label>Email address<input class="input mt-2 w-full" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
<label>Password<input class="input mt-2 w-full" type="password" name="password" required autocomplete="current-password"></label>
<div class="flex flex-wrap items-center justify-between gap-3 text-sm">
  <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
  <a class="text-mark" href="{{ route('password.request') }}">Forgot your password?</a>
</div>
<button class="btn btn-mark" type="submit">Sign in</button></form>
<p class="mt-6 text-dim">New to MWCN? <a class="text-mark" href="{{ route('register') }}">Create your free account</a>.</p>
<p class="mt-3 text-xs text-dim">MusiciansWorld team member? <a class="text-mark" href="{{ route('admin.login') }}">Use the Admin Login</a>.</p>
</div></section><?php page_end(); ?>
