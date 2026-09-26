<?php page_start('Sign in ? MusiciansWorld', 'Sign in to your connected MWCN account.'); ?>
<section class="section"><div class="wrap max-w-xl"><p class="kicker">One MWCN account</p><h1 class="display mt-3 text-5xl">Sign in</h1>
@if($errors->any())<div class="mt-6 border border-red-500/50 p-4 text-red-300">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ request()->is('admin/*') ? url('/admin/login.php') : url('/login') }}" class="mt-8 grid gap-5">@csrf
<label>Email or admin username<input class="input mt-2 w-full" type="text" name="email" value="{{ old('email') }}" required autocomplete="username"></label>
<label>Password<input class="input mt-2 w-full" type="password" name="password" required autocomplete="current-password"></label>
<a class="text-sm text-mark" href="{{ route('password.request') }}">Forgot your password?</a>
<label class="flex gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
<button class="btn btn-mark" type="submit">Sign in</button></form>
<p class="mt-6 text-dim">New to MWCN? <a class="text-mark" href="{{ route('register') }}">Create your free account</a>.</p></div></section><?php page_end(); ?>
