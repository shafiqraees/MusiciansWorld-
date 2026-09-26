<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingPageController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketController;
use App\Models\MembershipPlan;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$pages = [
    '/' => 'pages.index',
    '/spaces' => 'pages.spaces',
    '/spaces/studio-a' => 'pages.spaces.studio-a',
    '/book' => 'pages.book',
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
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::patch('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
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

Route::middleware(['auth', 'role:operations,finance,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/index.php', [AdminController::class, 'dashboard'])->middleware('permission:dashboard.view');
    Route::get('/bookings', [AdminController::class, 'bookings'])->middleware('permission:bookings.view')->name('bookings');
    Route::get('/bookings.php', [AdminController::class, 'bookings'])->middleware('permission:bookings.view');
    Route::patch('/bookings/{booking}/payment', [AdminController::class, 'bookingStatus'])->middleware('permission:bookings.manage')->name('bookings.payment');
    Route::get('/events', [AdminController::class, 'events'])->middleware('permission:events.view')->name('events');
    Route::patch('/events/{event}/review', [AdminController::class, 'reviewEvent'])->middleware('permission:events.approve')->name('events.review');
    Route::post('/events/{event}/settlement/calculate', [AdminController::class, 'calculateSettlement'])->middleware('permission:settlements.manage')->name('settlements.calculate');
    Route::post('/settlements/{settlement}/transfer', [AdminController::class, 'transferSettlement'])->middleware('permission:settlements.manage')->name('settlements.transfer');
    Route::get('/settings', [AdminController::class, 'settings'])->middleware('permission:settings.manage')->name('settings');
    Route::get('/memberships', [AdminController::class, 'settings'])->middleware('permission:memberships.manage')->name('memberships');
    Route::patch('/settings/{setting}', [AdminController::class, 'updateSetting'])->middleware('permission:settings.manage')->name('settings.update');
    Route::patch('/membership-plans/{plan}', [AdminController::class, 'updatePlan'])->middleware('permission:memberships.manage')->name('plans.update');
    Route::patch('/membership-plans/{plan}/benefits/{benefit}', [AdminController::class, 'updateBenefit'])->middleware('permission:memberships.manage')->name('benefits.update');
    Route::patch('/pricing-rules/{rule}', [AdminController::class, 'updatePricing'])->middleware('permission:settings.manage')->name('pricing.update');
    Route::post('/availability-holds', [AdminController::class, 'createHold'])->middleware('permission:bookings.manage')->name('holds.create');
    Route::delete('/availability-holds/{hold}', [AdminController::class, 'deleteHold'])->middleware('permission:bookings.manage')->name('holds.delete');
    Route::get('/failures', [AdminController::class, 'failures'])->middleware('permission:integrations.manage')->name('failures');
    Route::post('/failures/{job}/retry', [AdminController::class, 'retryFailure'])->middleware('permission:integrations.manage')->name('failures.retry');
    Route::get('/reports', [AdminController::class, 'reports'])->middleware('permission:reports.view')->name('reports');
    Route::get('/audit', [AdminController::class, 'auditLogs'])->middleware('permission:audit.view')->name('audit');
    Route::get('/members.php', [AdminController::class, 'customers'])->middleware('permission:users.view')->name('customers');
    Route::patch('/customers/{user}/roles', [AdminController::class, 'updateRoles'])->middleware('permission:roles.manage')->name('customers.roles');
    Route::get('/roles', [AdminController::class, 'roles'])->middleware('permission:roles.manage')->name('roles');
    Route::patch('/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions'])->middleware('permission:roles.manage')->name('roles.permissions');
    Route::get('/leads.php', [AdminController::class, 'submissions'])->middleware('permission:submissions.view')->name('submissions');
    Route::get('/export.php', [AdminController::class, 'export'])->middleware('permission:reports.export')->name('export');
});
Route::get('/admin/login.php', [AuthController::class, 'loginForm'])->middleware('guest');
Route::post('/admin/login.php', [AuthController::class, 'login'])->middleware('guest');
Route::get('/admin/logout.php', fn (Request $request) => app(AuthController::class)->logout($request))->middleware('auth');

foreach (['availability' => 'availability', 'bookings' => 'bookings'] as $path => $name) {
    Route::get("/api/$path", [BookingController::class, $name === 'availability' ? 'availability' : 'show']);
    Route::get("/api/$path.php", [BookingController::class, $name === 'availability' ? 'availability' : 'show']);
}
Route::post('/api/bookings', [BookingController::class, 'store']);
Route::post('/api/bookings.php', [BookingController::class, 'store']);
foreach (['contact', 'enroll', 'membership', 'dj-lesson-inquiry' => 'djLesson', 'soundtunnel-request' => 'soundtunnel', 'dj-school-request' => 'djSchool'] as $path => $method) {
    if (is_int($path)) {
        $path = $method;
    }
    Route::post("/api/$path", [FormController::class, $method]);
    Route::post("/api/$path.php", [FormController::class, $method]);
}
Route::get('/api/membership', [FormController::class, 'membershipStatus']);
Route::get('/api/membership.php', [FormController::class, 'membershipStatus']);
Route::post('/api/stripe-checkout', [StripeController::class, 'checkout']);
Route::post('/api/stripe-checkout.php', [StripeController::class, 'checkout']);
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
