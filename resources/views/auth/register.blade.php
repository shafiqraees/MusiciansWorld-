<?php page_start('Create your account ? MusiciansWorld', 'Create one free account for memberships, bookings, DJ School, events and tickets.'); ?>
<section class="section"><div class="wrap max-w-2xl"><p class="kicker">Free Community membership included</p><h1 class="display mt-3 text-5xl">Create your MWCN account</h1>
@if($errors->any())<div class="mt-6 border border-red-500/50 p-4 text-red-300"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" class="mt-8 grid gap-5 md:grid-cols-2">@csrf
<label class="md:col-span-2">Full name<input class="input mt-2 w-full" name="name" value="{{ old('name') }}" required></label>
<label>Email<input class="input mt-2 w-full" type="email" name="email" value="{{ old('email') }}" required></label><label>Phone<input class="input mt-2 w-full" name="phone" value="{{ old('phone') }}"></label>
<label>City<input class="input mt-2 w-full" name="city" value="{{ old('city') }}"></label><label>State<input class="input mt-2 w-full" name="state" value="{{ old('state') }}"></label>
<label>ZIP<input class="input mt-2 w-full" name="postal_code" value="{{ old('postal_code') }}"></label><span></span>
<label>Password<input class="input mt-2 w-full" type="password" name="password" required></label><label>Confirm password<input class="input mt-2 w-full" type="password" name="password_confirmation" required></label>
<label class="md:col-span-2 flex gap-2"><input type="checkbox" name="marketing" value="1"> Send me MWCN updates and opportunities.</label>
<button class="btn btn-mark md:col-span-2" type="submit">Create free account</button></form></div></section><?php page_end(); ?>
