<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingPageController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketController;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$pages = [
    '/' => 'pages.index',
    '/spaces' => 'pages.spaces',
    '/spaces/studio-a' => 'pages.spaces.studio-a',
    '/book/dj-lesson' => 'pages.book.dj-lesson',
    '/soundtunnel' => 'pages.soundtunnel',
    '/dj-school' => 'pages.dj-school',
    '/membership' => 'pages.membership',
    '/events' => 'pages.events',
    '/about' => 'pages.about',
    '/contact' => 'pages.contact',
];
foreach ($pages as $path => $view) {
    Route::view($path, $view);
}
Route::redirect('/soundtunnel/night', '/dj-school', 301);
Route::redirect('/soundtunnel-night.php', '/dj-school', 301);
foreach ([
    '/index.php' => 'pages.index', '/spaces.php' => 'pages.spaces',
    '/spaces/studio-a.php' => 'pages.spaces.studio-a', '/book.php' => 'pages.book',
    '/book/dj-lesson.php' => 'pages.book.dj-lesson', '/soundtunnel.php' => 'pages.soundtunnel',
    '/dj-school.php' => 'pages.dj-school',
    '/membership.php' => 'pages.membership', '/events.php' => 'pages.events',
    '/about.php' => 'pages.about', '/contact.php' => 'pages.contact',
] as $path => $view) {
    Route::view($path, $view);
}

Route::get('/book', [BookingPageController::class, 'index'])->name('booking.create');
Route::get('/book/availability', [BookingController::class, 'availability'])->name('booking.availability');
Route::post('/bookings', [BookingController::class, 'store'])->name('booking.store');
Route::get('/book/confirm/{id}', [BookingPageController::class, 'confirm'])->name('booking.confirm');
Route::get('/book/success', [BookingPageController::class, 'success'])->name('booking.success');
Route::get('/book/cancelled', [BookingPageController::class, 'cancelled'])->name('booking.cancelled');
Route::get('/book/success.php', [BookingPageController::class, 'success']);
Route::get('/book/cancelled.php', [BookingPageController::class, 'cancelled']);
Route::get('/book/confirm.php', fn (Request $request) => app(BookingPageController::class)->confirm($request->string('id')->toString()));

Route::get('/register', [AuthController::class, 'registerForm'])->middleware('guest')->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::get('/login', [AuthController::class, 'loginForm'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('guest')->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('guest')->name('password.update');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
    // Customer portal — one page per sidebar module
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/membership', [AccountController::class, 'membership'])->name('account.membership');
    Route::post('/account/membership/cancel', [AccountController::class, 'cancelMembership'])->name('account.membership.cancel');
    Route::post('/account/membership/resume', [AccountController::class, 'resumeMembership'])->name('account.membership.resume');
    Route::get('/account/benefits', [AccountController::class, 'benefits'])->name('account.benefits');
    Route::get('/account/bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::post('/account/bookings/{booking}/change-request', [AccountController::class, 'requestBookingChange'])->name('account.bookings.change');
    Route::get('/account/dj-school', [AccountController::class, 'school'])->name('account.school');
    Route::get('/account/tickets', [AccountController::class, 'tickets'])->name('account.tickets');
    Route::get('/account/events', [AccountController::class, 'events'])->name('account.events');
    Route::get('/account/events/{event}', [AccountController::class, 'eventDetail'])->whereNumber('event')->name('account.events.show');
    Route::get('/account/payments', [AccountController::class, 'payments'])->name('account.payments');
    Route::get('/account/payments/{transaction}', [AccountController::class, 'receipt'])->name('account.receipts.show');
    Route::get('/host-an-event', [EventController::class, 'create'])->name('organizer.events.create');
    Route::post('/host-an-event', [EventController::class, 'store'])->name('organizer.events.store');
    Route::get('/account/events/{event}/edit', [EventController::class, 'edit'])->name('organizer.events.edit');
    Route::put('/account/events/{event}', [EventController::class, 'update'])->name('organizer.events.update');
    Route::post('/account/events/{event}/submit', [EventController::class, 'submit'])->name('organizer.events.submit');
    Route::get('/tickets/{ticket}/qr', [TicketController::class, 'qr'])->name('tickets.qr');
    Route::get('/account/stripe-connect/start', [ConnectController::class, 'start'])->name('connect.start');
    Route::get('/account/stripe-connect/return', [ConnectController::class, 'returned'])->name('connect.return');
});

Route::get('/events/live', [EventController::class, 'index'])->name('events.live');
Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');
Route::post('/events/{event}/tickets', [TicketController::class, 'purchase'])->middleware('auth')->name('tickets.purchase');
Route::get('/tickets/success', [TicketController::class, 'success'])->name('tickets.success');
Route::get('/check-in/{ticket}', [TicketController::class, 'checkIn'])->middleware(['auth', 'role:check_in,operations,super_admin'])->name('tickets.check-in');

// Internal admin / staff sign-in lives at /admin/login (separate from the customer sign-in at /login).
Route::get('/admin/login', [AuthController::class, 'adminLoginForm'])->middleware('guest')->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('guest')->name('admin.login.attempt');
Route::get('/admin/login.php', fn () => redirect()->route('admin.login'));
Route::post('/admin/logout', [AuthController::class, 'logout'])->middleware('auth')->name('admin.logout');
Route::get('/admin/logout.php', fn (Request $request) => app(AuthController::class)->logout($request))->middleware('auth');

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/index.php', [AdminController::class, 'dashboard'])->middleware('permission:dashboard.view');

    // Bookings
    Route::get('/bookings', [AdminController::class, 'bookings'])->middleware('permission:bookings.view')->name('bookings');
    Route::get('/bookings.php', [AdminController::class, 'bookings'])->middleware('permission:bookings.view');
    Route::patch('/bookings/{booking}', [AdminController::class, 'updateBooking'])->middleware('permission:bookings.manage')->name('bookings.update');
    Route::patch('/bookings/{booking}/payment', [AdminController::class, 'updateBooking'])->middleware('permission:bookings.manage')->name('bookings.payment');

    // Events & settlements
    Route::get('/events', [AdminController::class, 'events'])->middleware('permission:events.view')->name('events');
    Route::put('/events/{event}', [AdminController::class, 'updateEvent'])->middleware('permission:events.approve')->name('events.update');
    Route::patch('/events/{event}/review', [AdminController::class, 'reviewEvent'])->middleware('permission:events.approve')->name('events.review');
    Route::post('/events/{event}/settlement/calculate', [AdminController::class, 'calculateSettlement'])->middleware('permission:settlements.manage')->name('settlements.calculate');
    Route::post('/settlements/{settlement}/transfer', [AdminController::class, 'transferSettlement'])->middleware('permission:settlements.manage')->name('settlements.transfer');

    // Memberships
    Route::get('/memberships', [MembershipController::class, 'index'])->middleware('permission:memberships.manage')->name('memberships');
    Route::post('/membership-plans', [MembershipController::class, 'storePlan'])->middleware('permission:memberships.manage')->name('plans.store');
    Route::patch('/membership-plans/{plan}', [MembershipController::class, 'updatePlan'])->middleware('permission:memberships.manage')->name('plans.update');
    Route::post('/customer-memberships', [MembershipController::class, 'storeMembership'])->middleware('permission:memberships.manage')->name('memberships.store');
    Route::patch('/customer-memberships/{membership}', [MembershipController::class, 'updateMembership'])->middleware('permission:memberships.manage')->name('memberships.update');

    // Settings (hub + sections)
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:settings.manage')->name('settings');
    Route::get('/settings/platform', [SettingsController::class, 'platform'])->middleware('permission:settings.manage')->name('settings.platform');
    Route::post('/settings/platform', [SettingsController::class, 'storeSetting'])->middleware('permission:settings.manage')->name('settings.store');
    Route::patch('/settings/{setting}', [SettingsController::class, 'updateSetting'])->middleware('permission:settings.manage')->name('settings.update');
    Route::get('/settings/pricing', [SettingsController::class, 'pricing'])->middleware('permission:settings.manage')->name('settings.pricing');
    Route::patch('/resources/{resource}', [SettingsController::class, 'updateResource'])->middleware('permission:settings.manage')->name('resources.update');
    Route::post('/pricing-rules', [SettingsController::class, 'storePricing'])->middleware('permission:settings.manage')->name('pricing.store');
    Route::patch('/pricing-rules/{rule}', [SettingsController::class, 'updatePricing'])->middleware('permission:settings.manage')->name('pricing.update');
    Route::get('/settings/availability', [SettingsController::class, 'availability'])->middleware('permission:bookings.manage')->name('settings.availability');
    Route::post('/availability-holds', [SettingsController::class, 'createHold'])->middleware('permission:bookings.manage')->name('holds.create');
    Route::delete('/availability-holds/{hold}', [SettingsController::class, 'deleteHold'])->middleware('permission:bookings.manage')->name('holds.delete');
    Route::get('/settings/integrations', [SettingsController::class, 'integrations'])->middleware('permission:settings.manage')->name('settings.integrations');

    // Users: shared listing for all / customers / admins / staff, plus the detail page
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users');
    Route::get('/customers', fn () => app(UserController::class)->index(request(), 'customers'))->middleware('permission:users.view')->name('customers');
    Route::get('/admins', fn () => app(UserController::class)->index(request(), 'admins'))->middleware('permission:users.view')->name('admins');
    Route::get('/staff', fn () => app(UserController::class)->index(request(), 'staff'))->middleware('permission:users.view')->name('staff');
    Route::get('/members.php', fn () => redirect()->route('admin.customers'));
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:users.manage')->name('users.status');
    Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])->middleware('permission:users.manage')->name('users.password');
    Route::patch('/users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('permission:roles.manage')->name('users.roles');
    Route::patch('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->middleware('permission:roles.manage')->name('users.permissions');

    // Roles
    Route::get('/roles', [AdminController::class, 'roles'])->middleware('permission:roles.manage')->name('roles');
    Route::patch('/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions'])->middleware('permission:roles.manage')->name('roles.permissions');

    // Leads, reporting, exports
    Route::get('/leads.php', [AdminController::class, 'submissions'])->middleware('permission:submissions.view')->name('submissions');
    Route::get('/reports', [AdminController::class, 'reports'])->middleware('permission:reports.view')->name('reports');
    Route::get('/transactions', [FinanceController::class, 'transactions'])->middleware('permission:reports.view')->name('transactions');
    Route::get('/export.php', [AdminController::class, 'export'])->middleware('permission:reports.export')->name('export');

    // Hidden from navigation for now, but still reachable for operators who need them.
    Route::get('/failures', [AdminController::class, 'failures'])->middleware('permission:integrations.manage')->name('failures');
    Route::post('/failures/{job}/retry', [AdminController::class, 'retryFailure'])->middleware('permission:integrations.manage')->name('failures.retry');
    Route::get('/audit', [AdminController::class, 'auditLogs'])->middleware('permission:audit.view')->name('audit');
});

$publicForms = [
    'contact' => 'contact',
    'enroll' => 'enroll',
    'membership' => 'membership',
    'dj-lesson' => 'djLesson',
    'soundtunnel' => 'soundtunnel',
    'dj-school' => 'djSchool',
];
foreach ($publicForms as $path => $method) {
    Route::post("/forms/$path", [FormController::class, $method])->name("forms.$path");
}
Route::get('/membership/status', [FormController::class, 'membershipStatus'])->name('membership.status');
Route::post('/membership/checkout', [StripeController::class, 'checkout'])->name('membership.checkout');
Route::post('/api/stripe-webhook', [StripeController::class, 'webhook']);
Route::post('/api/stripe-webhook.php', [StripeController::class, 'webhook']);

Route::get('/membership-success.php', function (Request $request, StripeService $stripe) {
    $active = false;
    if ($request->filled('session_id')) {
        try {
            $session = $stripe->client()->checkout->sessions->retrieve($request->string('session_id')->toString());
            $active = in_array($session->status, ['complete'], true);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    return view('membership-success', compact('active'));
})->name('membership.success');
