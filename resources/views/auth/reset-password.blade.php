<?php page_start('Choose a new password — MusiciansWorld'); ?>
<section class="section"><div class="wrap max-w-xl"><p class="kicker">Account recovery</p><h1 class="display mt-3 text-5xl">Choose a new password</h1>
@if($errors->any())<p class="mt-5 text-red-300">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('password.update') }}" class="mt-8 grid gap-5">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label>Email<input class="input mt-2 w-full" type="email" name="email" value="{{ $email }}" required></label>
<label>Password<input class="input mt-2 w-full" type="password" name="password" required></label>
<label>Confirm password<input class="input mt-2 w-full" type="password" name="password_confirmation" required></label>
<button class="btn btn-mark">Update password</button></form></div></section>
<?php page_end(); ?>
