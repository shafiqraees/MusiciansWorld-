<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin sign in — MWCN Portal</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="icon" href="{{ url('assets/brand/favicon.png') }}" type="image/png">
  <script>(function(){try{var t=localStorage.getItem('mwcn-admin-theme');if(t==='light'||t==='dark'){document.documentElement.dataset.theme=t;}}catch(e){}})();</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ url('assets/css/admin-portal.css') }}?v=20261006a">
</head>
<body class="mw-admin-body">
<div class="a-auth">
  <aside class="a-auth-aside">
    <div>
      <div class="a-auth-top"><img src="{{ url('assets/brand/logo.png') }}" alt="MusiciansWorld Creative Network"></div>
      <p class="a-kicker" style="margin-top:36px">Internal operations portal</p>
      <h1>Run the network from one desk.</h1>
      <p>Bookings, events, memberships, finance and access control for the MusiciansWorld team. This sign-in is reserved for internal admins and staff.</p>
      <ul class="a-auth-points">
        <li>Role-based permissions with a full audit trail</li>
        <li>Stripe, calendar and email automation in one place</li>
        <li>Light and dark themes for day or night shifts</li>
      </ul>
    </div>
    <p style="color:var(--a-muted);font-size:12px;margin:0">© {{ date('Y') }} MusiciansWorld Creative Network</p>
  </aside>
  <main class="a-auth-main">
    <div class="a-auth-card">
      <div class="a-auth-top">
        <div><p class="a-kicker">Admin login</p><h2>Sign in to the portal</h2></div>
        <button class="a-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
          <span><svg class="a-icon-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg><svg class="a-icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
        </button>
      </div>
      <p>Use your internal team credentials. Customer accounts should use the <a class="a-link" href="{{ route('login') }}">customer sign-in</a>.</p>
      @if(session('success'))<div class="a-alert success" style="margin:18px 0 0">{{ session('success') }}</div>@endif
      @if($errors->any())<div class="a-alert error" style="margin:18px 0 0" role="alert">{{ $errors->first() }}</div>@endif
      <form method="post" action="{{ route('admin.login.attempt') }}" class="a-auth-form">
        @csrf
        <label class="a-field"><span>Work email</span><input class="a-input" type="text" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus placeholder="you@musiciansworld.com"></label>
        <label class="a-field"><span>Password</span><input class="a-input" type="password" name="password" required autocomplete="current-password" placeholder="••••••••••"></label>
        <div class="a-auth-meta">
          <label class="a-check is-inline"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
          <a class="a-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="a-btn primary block" type="submit" style="min-height:46px">Sign in to the portal</button>
      </form>
      <p class="a-auth-foot">Access is logged. Contact a Super Admin if you need portal access.</p>
    </div>
  </main>
</div>
<script src="{{ url('assets/js/admin-portal.js') }}?v=20261006a" defer></script>
</body>
</html>
