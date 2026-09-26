<?php page_start('Reset password — MusiciansWorld'); ?>
<section class="section"><div class="wrap max-w-xl"><p class="kicker">Account recovery</p><h1 class="display mt-3 text-5xl">Reset your password</h1>
@if(session('success'))<p class="mt-5 text-green-300">{{ session('success') }}</p>@endif
@error('email')<p class="mt-5 text-red-300">{{ $message }}</p>@enderror
<form method="post" action="{{ route('password.email') }}" class="mt-8 grid gap-5">@csrf
<label>Email<input class="input mt-2 w-full" type="email" name="email" value="{{ old('email') }}" required></label>
<button class="btn btn-mark">Send reset link</button></form></div></section>
<?php page_end(); ?>
