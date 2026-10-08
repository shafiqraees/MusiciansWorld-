<?php
global $SITE, $MEDIA;
$page_title = $page_title ?? ($SITE["name"] . " — Arizona’s creator hub");
$page_desc = $page_desc ?? ("Create. Connect. Learn. Perform. Grow. Studio A, SoundTunnel, DJ School, and membership. Open 9AM–2AM.");
$nav_links = [
    ["/spaces", "Spaces"],
    ["/dj-school", "DJ School"],
    ["/soundtunnel", "SoundTunnel"],
    ["/membership", "Membership"],
    ["/events", "Events"],
    ["/about", "About"],
    ["/contact", "Contact"],
];
$logo = url($MEDIA["logo"]);
$headerAction = auth()->check()
    ? [auth()->user()->isStaffAccount() ? route('admin.dashboard') : route('account.dashboard'), 'Dashboard']
    : [route('login'), 'Login'];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($page_desc) ?>" />
  <link rel="icon" href="<?= e(url("assets/brand/favicon.png")) ?>" type="image/png" />
  <link rel="apple-touch-icon" href="<?= e(url("assets/brand/apple-icon.png")) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(url("assets/css/app.css")) ?>?v=20260909e" />
  <link rel="stylesheet" href="<?= e(url("assets/css/theme.css")) ?>?v=20260909e" />
  <link rel="stylesheet" href="<?= e(url("assets/css/fix.css")) ?>?v=20260909e" />
  <link rel="stylesheet" href="<?= e(url("assets/css/phase2.css")) ?>?v=20261007h" />
  <?php if (!empty($GLOBALS["page_styles"])): foreach ($GLOBALS["page_styles"] as $href): ?>
  <link rel="stylesheet" href="<?= e(url($href)) ?>?v=20260926a" />
  <?php endforeach; endif; ?>
  <script>
    window.MW = {
      base: <?= json_encode(site_base()) ?>,
      locationLabel: <?= json_encode($SITE["locationLabel"]) ?>,
      csrfToken: <?= json_encode(csrf_token()) ?>,
      customer: <?= json_encode(auth()->check() ? [
          'name' => auth()->user()->name,
          'email' => auth()->user()->email,
          'phone' => auth()->user()->phone,
          'city' => auth()->user()->city,
          'state' => auth()->user()->state,
          'zip' => auth()->user()->postal_code,
      ] : null) ?>,
      routes: <?= json_encode([
          'contact' => route('forms.contact'),
          'soundtunnel' => route('forms.soundtunnel'),
          'dj-school' => route('forms.dj-school'),
          'enroll' => route('forms.enroll'),
          'membership' => route('forms.membership'),
          'membership-checkout' => route('membership.checkout'),
          'dj-lesson' => route('forms.dj-lesson'),
      ]) ?>
    };
  </script>
</head>
<body class="flex min-h-full flex-col bg-bg font-sans text-text antialiased">
  <header id="site-header" class="sticky top-0 z-50 bg-transparent transition-all duration-300">
    <div class="wrap flex h-16 items-center gap-3 sm:h-[72px] lg:h-[100px] lg:gap-5">
      <a href="<?= e(url("/")) ?>" class="flex min-w-0 shrink-0 items-center" aria-label="Musicians World home">
        <img src="<?= e($logo) ?>" alt="Musicians World Creative Network" class="h-12 w-auto mix-blend-screen sm:h-14 lg:h-[88px]" />
      </a>
      <nav class="hidden flex-1 items-center justify-center gap-5 text-[13px] tracking-[0.12em] text-dim uppercase xl:gap-7 lg:flex">
        <?php foreach ($nav_links as [$href, $label]):
            if ($href === "/contact") continue;
            $active = path_active($href);
        ?>
          <a href="<?= e(url($href)) ?>" class="nav-link <?= $active ? "is-active text-text" : "hover:text-text" ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="ml-auto flex items-center gap-2 lg:ml-0">
        <a href="<?= e($headerAction[0]) ?>" class="btn btn-ghost min-h-11 px-3 text-[11px] tracking-[0.12em] uppercase lg:min-h-[50px] lg:px-5 lg:text-[12px]"><?= e($headerAction[1]) ?></a>
        <a href="<?= e(url("/book")) ?>" class="nav-cta btn btn-mark min-h-11 px-4 text-[11px] lg:min-h-[50px] lg:px-6 lg:text-[12px]">Book a space</a>
        <button type="button" id="menu-open" class="grid h-11 w-11 place-items-center border border-line lg:hidden" aria-expanded="false" aria-controls="mobile-sidebar" aria-label="Open menu">
          <span class="flex flex-col gap-1.5">
            <span class="menu-bar-1 block h-px w-5 bg-text transition"></span>
            <span class="menu-bar-2 block h-px w-5 bg-text transition"></span>
            <span class="menu-bar-3 block h-px w-5 bg-text transition"></span>
          </span>
        </button>
      </div>
    </div>
  </header>

  <button type="button" id="menu-overlay" class="pointer-events-none fixed inset-0 z-[70] bg-black/70 opacity-0 transition-opacity duration-300 lg:hidden" aria-label="Close menu" tabindex="-1"></button>
  <aside id="mobile-sidebar" class="pointer-events-none fixed top-0 right-0 z-[80] flex h-dvh w-[min(86vw,360px)] translate-x-full flex-col border-l border-line bg-[#07070f] shadow-[-24px_0_60px_rgba(0,0,0,0.55)] transition-transform duration-300 ease-out lg:hidden" aria-hidden="true">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
      <img src="<?= e($logo) ?>" alt="" class="h-14 w-auto mix-blend-screen" />
      <button type="button" id="menu-close" class="grid h-11 w-11 place-items-center border border-line" aria-label="Close menu">
        <span class="text-xl leading-none">×</span>
      </button>
    </div>
    <nav class="flex-1 overflow-y-auto px-5 py-6">
      <?php foreach ($nav_links as [$href, $label]):
          $active = path_active($href);
      ?>
        <a href="<?= e(url($href)) ?>" class="block border-b border-line py-4 text-sm tracking-[0.16em] uppercase <?= $active ? "text-mark" : "text-text" ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
        <a href="<?= e($headerAction[0]) ?>" class="block border-b border-line py-4 text-sm tracking-[0.16em] uppercase <?= auth()->check() && path_active('/account') ? 'text-mark' : 'text-text' ?>"><?= e($headerAction[1]) ?></a>
    </nav>
    <div class="border-t border-line p-5">
      <a href="<?= e(url("/book")) ?>" class="btn btn-mark w-full">Book a space</a>
    </div>
  </aside>

  <main class="flex-1">
