<?php
$adminUser = auth()->user();
$adminRoleLabel = $adminUser?->hasRole('super_admin') ? 'Super Admin' : ($adminUser?->roles->pluck('label')->first() ?? 'Staff');
$adminTitle = preg_replace('/^MWCN ERP\s*(?:\?|—|-)\s*/u', '', $page_title ?? 'Dashboard');
$adminTitle = str_replace([' — MWCN ERP', ' ? MWCN ERP', ' — MusiciansWorld', ' ? MusiciansWorld'], '', $adminTitle);
$pendingEvents = \App\Models\Event::where('status', 'submitted')->count();
$failedJobs = \App\Models\IntegrationJob::where('status', 'failed')->count();
$newSubmissions = \App\Models\FormSubmission::where('status', 'new')->count();
$menu = [
    ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
    ['section' => 'People & access'],
    ['label' => 'Users', 'route' => 'admin.customers', 'icon' => 'users', 'permission' => 'users.view'],
    ['label' => 'Memberships & benefits', 'route' => 'admin.memberships', 'icon' => 'badge', 'permission' => 'memberships.manage'],
    ['label' => 'Roles & permissions', 'route' => 'admin.roles', 'icon' => 'shield', 'permission' => 'roles.manage'],
    ['label' => 'Leads & submissions', 'route' => 'admin.submissions', 'icon' => 'inbox', 'badge' => $newSubmissions, 'permission' => 'submissions.view'],
    ['section' => 'Operations'],
    ['label' => 'Bookings', 'route' => 'admin.bookings', 'icon' => 'calendar', 'permission' => 'bookings.view'],
    ['label' => 'Events & approvals', 'route' => 'admin.events', 'icon' => 'ticket', 'badge' => $pendingEvents, 'permission' => 'events.view'],
    ['label' => 'Automation center', 'route' => 'admin.failures', 'icon' => 'activity', 'badge' => $failedJobs, 'permission' => 'integrations.manage'],
    ['section' => 'Intelligence'],
    ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart', 'permission' => 'reports.view'],
    ['label' => 'Audit trail', 'route' => 'admin.audit', 'icon' => 'history', 'permission' => 'audit.view'],
    ['label' => 'Platform settings', 'route' => 'admin.settings', 'icon' => 'settings', 'permission' => 'settings.manage'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($adminTitle ?: 'Dashboard').' — MWCN ERP') ?></title>
  <link rel="icon" href="<?= e(url('assets/brand/favicon.png')) ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/admin-portal.css')) ?>?v=20260926a">
</head>
<body class="mw-admin-body">
<svg class="admin-svg-sprite" aria-hidden="true">
  <symbol id="ai-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
  <symbol id="ai-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
  <symbol id="ai-badge" viewBox="0 0 24 24"><path d="m12 2 3 3 4.2-.6-.6 4.2 3 3-3 3 .6 4.2-4.2-.6-3 3-3-3-4.2.6.6-4.2-3-3 3-3-.6-4.2 4.2.6Z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="ai-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="ai-inbox" viewBox="0 0 24 24"><path d="M4 4h16l2 11v5H2v-5L4 4Z"/><path d="M2 15h6l2 3h4l2-3h6"/></symbol>
  <symbol id="ai-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></symbol>
  <symbol id="ai-ticket" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 0 0 6v4h20v-4a3 3 0 0 0 0-6V5H2v4Z"/><path d="M13 5v2M13 11v2M13 17v2"/></symbol>
  <symbol id="ai-activity" viewBox="0 0 24 24"><path d="M3 12h4l2-7 4 14 2-7h6"/></symbol>
  <symbol id="ai-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
  <symbol id="ai-history" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></symbol>
  <symbol id="ai-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .4 2l.1.1-2.8 2.8-.1-.1a1.8 1.8 0 0 0-2-.4 1.8 1.8 0 0 0-1.1 1.6v.2h-4V21A1.8 1.8 0 0 0 8.8 19.4a1.8 1.8 0 0 0-2 .4l-.1.1-2.8-2.8.1-.1a1.8 1.8 0 0 0 .4-2A1.8 1.8 0 0 0 2.8 14h-.2v-4h.2A1.8 1.8 0 0 0 4.4 9a1.8 1.8 0 0 0-.4-2l-.1-.1 2.8-2.8.1.1a1.8 1.8 0 0 0 2 .4A1.8 1.8 0 0 0 10 3V2.8h4V3a1.8 1.8 0 0 0 1 1.6 1.8 1.8 0 0 0 2-.4l.1-.1 2.8 2.8-.1.1a1.8 1.8 0 0 0-.4 2 1.8 1.8 0 0 0 1.6 1h.2v4H21a1.8 1.8 0 0 0-1.6 1Z"/></symbol>
</svg>
<div class="admin-shell">
  <button class="admin-overlay" type="button" data-admin-close aria-label="Close navigation"></button>
  <aside class="admin-sidebar" id="admin-sidebar">
    <a class="admin-brand" href="<?= e(route('admin.dashboard')) ?>">
      <img src="<?= e(url('assets/brand/logo.png')) ?>" alt="MusiciansWorld">
      <span><strong>MWCN</strong><small>Operations Portal</small></span>
    </a>
    <div class="admin-user-card">
      <span class="admin-avatar"><?= e(strtoupper(substr($adminUser?->name ?? 'A', 0, 1))) ?></span>
      <span class="admin-user-copy"><strong><?= e($adminUser?->name ?? 'Administrator') ?></strong><small><?= e($adminRoleLabel) ?></small></span>
      <span class="admin-online" title="Signed in"></span>
    </div>
    <nav class="admin-nav" aria-label="Admin navigation">
      <?php foreach ($menu as $item): ?>
        <?php if (isset($item['section'])): ?><p class="admin-nav-section"><?= e($item['section']) ?></p><?php continue; endif; ?>
        <?php if (!empty($item['permission']) && !$adminUser->hasPermission($item['permission'])) continue; ?>
        <a class="admin-nav-link <?= request()->routeIs($item['route']) ? 'is-active' : '' ?>" href="<?= e(route($item['route'])) ?>">
          <svg><use href="#ai-<?= e($item['icon']) ?>"></use></svg><span><?= e($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?><b><?= (int) $item['badge'] ?></b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="<?= e(url('/')) ?>" target="_blank">View website <span>↗</span></a>
      <form method="post" action="<?= e(route('logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </div>
  </aside>
  <div class="admin-workspace">
    <header class="admin-topbar">
      <button class="admin-menu-toggle" type="button" data-admin-open aria-controls="admin-sidebar" aria-label="Open navigation"><span></span><span></span><span></span></button>
      <div><p>MusiciansWorld Creative Network</p><h1><?= e($adminTitle ?: 'Dashboard') ?></h1></div>
      <div class="admin-top-actions">
        <a href="<?= e(route('admin.failures')) ?>" class="admin-alert-button" aria-label="Automation notifications"><svg><use href="#ai-activity"></use></svg><?php if ($failedJobs): ?><b><?= (int) $failedJobs ?></b><?php endif; ?></a>
        <span class="admin-date"><?= e(now()->format('D, M j')) ?></span>
      </div>
    </header>
    <main class="admin-content">
