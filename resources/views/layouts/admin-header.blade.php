<?php
$adminUser = auth()->user();
$adminRoleLabel = $adminUser?->hasRole('super_admin') ? 'Super Admin' : ($adminUser?->roles->pluck('label')->first() ?? $adminUser?->accountTypeLabel() ?? 'Staff');
$adminTitle = preg_replace('/^MWCN ERP\s*(?:\?|—|-)\s*/u', '', $page_title ?? 'Dashboard');
$adminTitle = str_replace([' — MWCN ERP', ' ? MWCN ERP', ' — MusiciansWorld', ' ? MusiciansWorld'], '', $adminTitle);
$pendingEvents = \App\Models\Event::where('status', 'submitted')->count();
$newSubmissions = \App\Models\FormSubmission::where('status', 'new')->count();
$menu = [
    ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
    ['section' => 'People & access'],
    ['label' => 'All users', 'route' => 'admin.users', 'icon' => 'users', 'permission' => 'users.view', 'match' => ['admin.users', 'admin.users.show']],
    ['label' => 'Customers', 'route' => 'admin.customers', 'icon' => 'user', 'permission' => 'users.view'],
    ['label' => 'Internal admins', 'route' => 'admin.admins', 'icon' => 'shield-user', 'permission' => 'users.view'],
    ['label' => 'Internal staff', 'route' => 'admin.staff', 'icon' => 'briefcase', 'permission' => 'users.view'],
    ['label' => 'Roles & permissions', 'route' => 'admin.roles', 'icon' => 'shield', 'permission' => 'roles.manage'],
    ['section' => 'Commerce'],
    ['label' => 'Memberships & benefits', 'route' => 'admin.memberships', 'icon' => 'badge', 'permission' => 'memberships.manage'],
    ['label' => 'Leads & submissions', 'route' => 'admin.submissions', 'icon' => 'inbox', 'badge' => $newSubmissions, 'permission' => 'submissions.view'],
    ['section' => 'Operations'],
    ['label' => 'Bookings', 'route' => 'admin.bookings', 'icon' => 'calendar', 'permission' => 'bookings.view'],
    ['label' => 'Events & approvals', 'route' => 'admin.events', 'icon' => 'ticket', 'badge' => $pendingEvents, 'permission' => 'events.view'],
    ['section' => 'Finance & configuration'],
    ['label' => 'Transactions', 'route' => 'admin.transactions', 'icon' => 'dollar', 'permission' => 'reports.view'],
    ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart', 'permission' => 'reports.view'],
    ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings', 'permission' => 'settings.manage', 'match' => ['admin.settings', 'admin.settings.*']],
];
$openModal = session('modal') ?? old('_modal');
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($adminTitle ?: 'Dashboard').' — MWCN Portal') ?></title>
  <link rel="icon" href="<?= e(url('assets/brand/favicon.png')) ?>" type="image/png">
  <script>(function(){try{var t=localStorage.getItem('mwcn-admin-theme');if(t==='light'||t==='dark'){document.documentElement.dataset.theme=t;}}catch(e){}})();</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/admin-portal.css')) ?>?v=20261007b">
</head>
<body class="mw-admin-body"<?= $openModal ? ' data-open-modal="'.e($openModal).'"' : '' ?><?= $openModal && old('_pane') ? ' data-open-pane="'.e(old('_pane')).'"' : '' ?>>
<?= view('admin.partials.icons')->render() ?>
<div class="admin-shell">
  <button class="admin-overlay" type="button" data-admin-close aria-label="Close navigation"></button>
  <aside class="admin-sidebar" id="admin-sidebar">
    <a class="admin-brand" href="<?= e(route('admin.dashboard')) ?>">
      <img src="<?= e(url('assets/brand/logo.png')) ?>" alt="MusiciansWorld">
      <span><strong>MWCN</strong><small>Operations Portal</small></span>
    </a>
    <a class="admin-user-card" href="<?= e(route('admin.users.show', $adminUser)) ?>" title="Open your profile">
      <span class="a-avatar"><?= e($adminUser?->initials() ?? 'A') ?></span>
      <span class="admin-user-copy"><strong><?= e($adminUser?->name ?? 'Administrator') ?></strong><small><?= e($adminRoleLabel) ?></small></span>
      <span class="admin-online" title="Signed in"></span>
    </a>
    <nav class="admin-nav" aria-label="Admin navigation">
      <?php foreach ($menu as $item): ?>
        <?php if (isset($item['section'])): ?><p class="admin-nav-section"><?= e($item['section']) ?></p><?php continue; endif; ?>
        <?php if (!empty($item['permission']) && !$adminUser->hasPermission($item['permission'])) continue; ?>
        <?php $active = request()->routeIs(...($item['match'] ?? [$item['route']])); ?>
        <a class="admin-nav-link <?= $active ? 'is-active' : '' ?>" href="<?= e(route($item['route'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <svg><use href="#ai-<?= e($item['icon']) ?>"></use></svg><span><?= e($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?><b><?= (int) $item['badge'] ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View website <span>↗</span></a>
      <form method="post" action="<?= e(route('admin.logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out <span>→</span></button></form>
    </div>
  </aside>
  <div class="admin-workspace">
    <header class="admin-topbar">
      <button class="admin-menu-toggle" type="button" data-admin-open aria-controls="admin-sidebar" aria-label="Open navigation"><span></span><span></span><span></span></button>
      <div><p>MusiciansWorld Creative Network</p><h1><?= e($adminTitle ?: 'Dashboard') ?></h1></div>
      <div class="admin-top-actions">
        <span class="admin-date"><?= e(now()->format('D, M j · g:i A')) ?></span>
        <button class="a-theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
          <span><svg class="a-icon-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg><svg class="a-icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
        </button>
      </div>
    </header>
    <main class="admin-content">
<?php if (session('success')): ?>
<div class="a-alert success" role="status" data-autodismiss style="margin-top:22px;margin-bottom:-6px"><svg class="a-icon" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg><div><?= e(session('success')) ?></div></div>
<?php endif; ?>
<?php if ($errors->any()): ?>
<div class="a-alert error" role="alert" style="margin-top:22px;margin-bottom:-6px"><svg class="a-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><div><?php if ($errors->count() === 1): ?><?= e($errors->first()) ?><?php else: ?><ul><?php foreach ($errors->all() as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul><?php endif; ?></div></div>
<?php endif; ?>
