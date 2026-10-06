<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\CustomerMembership;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\FormSubmission;
use App\Models\IntegrationJob;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSettlement;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $password = Hash::make('Demo1234');
            $roles = Role::pluck('id', 'name');
            $plans = MembershipPlan::pluck('id', 'slug');
            $studio = Resource::where('slug', 'studio-a')->firstOrFail();
            $soundTunnel = Resource::where('slug', 'soundtunnel')->firstOrFail();

            $accounts = [
                'operations' => ['name' => 'Olivia Operations', 'email' => 'operations@musiciansworld.test', 'phone' => '602-555-0101', 'city' => 'Phoenix'],
                'finance' => ['name' => 'Frankie Finance', 'email' => 'finance@musiciansworld.test', 'phone' => '602-555-0102', 'city' => 'Tempe'],
                'organizer' => ['name' => 'Maya Promoter', 'email' => 'organizer@musiciansworld.test', 'phone' => '602-555-0103', 'city' => 'Scottsdale'],
                'instructor' => ['name' => 'Andre Instructor', 'email' => 'instructor@musiciansworld.test', 'phone' => '602-555-0104', 'city' => 'Mesa'],
                'check_in' => ['name' => 'Casey Check-In', 'email' => 'checkin@musiciansworld.test', 'phone' => '602-555-0105', 'city' => 'Phoenix'],
                'customer' => ['name' => 'Jordan Customer', 'email' => 'customer@musiciansworld.test', 'phone' => '602-555-0106', 'city' => 'Glendale'],
            ];

            $users = [];
            foreach ($accounts as $role => $account) {
                $users[$role] = User::updateOrCreate(
                    ['email' => $account['email']],
                    $account + [
                        'state' => 'AZ',
                        'postal_code' => '85004',
                        'status' => 'active',
                        'account_type' => in_array($role, Role::ADMIN_ROLES, true) ? User::TYPE_ADMIN : (in_array($role, Role::STAFF_ROLES, true) ? User::TYPE_STAFF : User::TYPE_CUSTOMER),
                        'email_verified_at' => now()->subMonths(2),
                        'communication_preferences' => ['email' => true, 'sms' => $role === 'customer'],
                        'password' => $password,
                    ],
                );
                $users[$role]->roles()->sync([$roles[$role]]);
            }

            foreach ([
                ['name' => 'Taylor Reed', 'email' => 'taylor.reed@example.test', 'status' => 'active', 'plan' => 'founder', 'membership' => 'active'],
                ['name' => 'Morgan Lee', 'email' => 'morgan.lee@example.test', 'status' => 'active', 'plan' => 'community', 'membership' => 'trialing'],
                ['name' => 'Sam Rivera', 'email' => 'sam.rivera@example.test', 'status' => 'inactive', 'plan' => 'community', 'membership' => 'cancelled'],
                ['name' => 'Avery Brooks', 'email' => 'avery.brooks@example.test', 'status' => 'active', 'plan' => 'founder', 'membership' => 'grace'],
            ] as $index => $customer) {
                $user = User::updateOrCreate(
                    ['email' => $customer['email']],
                    [
                        'name' => $customer['name'],
                        'phone' => '602-555-02'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'city' => ['Phoenix', 'Tempe', 'Mesa', 'Chandler'][$index],
                        'state' => 'AZ',
                        'postal_code' => '8500'.($index + 1),
                        'status' => $customer['status'],
                        'email_verified_at' => $index === 2 ? null : now()->subDays(30 - $index),
                        'password' => $password,
                    ],
                );
                $user->roles()->sync([$roles['customer']]);
                CustomerMembership::updateOrCreate(
                    ['user_id' => $user->id, 'membership_plan_id' => $plans[$customer['plan']]],
                    [
                        'status' => $customer['membership'],
                        'stripe_customer_id' => 'cus_demo_'.$user->id,
                        'stripe_subscription_id' => $customer['plan'] === 'founder' ? 'sub_demo_'.$user->id : null,
                        'current_period_start' => now()->subDays(10),
                        'current_period_end' => now()->addDays(20),
                        'grace_ends_at' => $customer['membership'] === 'grace' ? now()->addDays(5) : null,
                        'cancelled_at' => $customer['membership'] === 'cancelled' ? now()->subDays(4) : null,
                    ],
                );
            }

            CustomerMembership::updateOrCreate(
                ['user_id' => $users['customer']->id, 'membership_plan_id' => $plans['founder']],
                [
                    'status' => 'active',
                    'stripe_customer_id' => 'cus_demo_primary',
                    'stripe_subscription_id' => 'sub_demo_primary',
                    'current_period_start' => now()->subDays(8),
                    'current_period_end' => now()->addDays(22),
                ],
            );

            OrganizerProfile::updateOrCreate(
                ['user_id' => $users['organizer']->id],
                [
                    'display_name' => 'Desert Pulse Events',
                    'status' => 'verified',
                    'is_verified' => true,
                    'stripe_account_id' => 'acct_demo_desert_pulse',
                    'stripe_charges_enabled' => true,
                    'stripe_payouts_enabled' => true,
                    'requirements' => [],
                ],
            );

            $bookingRows = [
                ['DEMO-BKG-1001', $users['customer'], 2, 2, 'confirmed', 'paid', 8000, 'Recording session with engineer', 'succeeded'],
                ['DEMO-BKG-1002', null, 5, 3, 'pending_payment', 'pending', 10500, 'Band rehearsal', null],
                ['DEMO-BKG-1003', null, 9, 4, 'failed', 'failed', 14000, 'Podcast production', 'failed'],
                ['DEMO-BKG-1004', null, -5, 2, 'completed', 'paid', 8000, 'Vocal tracking session', 'succeeded'],
                ['DEMO-BKG-1005', null, -12, 3, 'refunded', 'refunded', 10500, 'Cancelled album session', 'succeeded'],
                ['DEMO-BKG-1006', null, -20, 2, 'cancelled', 'cancelled', 8000, 'Cancelled rehearsal', null],
            ];
            $bookings = [];
            foreach ($bookingRows as $index => [$reference, $user, $dayOffset, $hours, $status, $payment, $total, $notes, $transactionStatus]) {
                $customer = $user ?: User::whereIn('email', ['taylor.reed@example.test', 'morgan.lee@example.test', 'avery.brooks@example.test'])->get()->values()[$index % 3];
                $startsAt = now()->addDays($dayOffset)->setTime(10 + ($index % 4) * 2, 0);
                $bookings[$reference] = Booking::updateOrCreate(
                    ['reference' => $reference],
                    [
                        'user_id' => $customer->id,
                        'resource_id' => $studio->id,
                        'service_type' => 'studio_booking',
                        'starts_at' => $startsAt,
                        'ends_at' => $startsAt->copy()->addHours($hours),
                        'hold_expires_at' => $payment === 'pending' ? now()->addMinutes(45) : null,
                        'status' => $status,
                        'payment_status' => $payment,
                        'subtotal_cents' => $total,
                        'discount_cents' => 0,
                        'total_cents' => $total,
                        'currency' => 'usd',
                        'customer_name' => $customer->name,
                        'customer_email' => $customer->email,
                        'customer_phone' => $customer->phone,
                        'notes' => $notes,
                        'stripe_checkout_session_id' => in_array($payment, ['paid', 'refunded'], true) ? 'cs_demo_'.$reference : null,
                        'stripe_payment_intent_id' => in_array($payment, ['paid', 'refunded'], true) ? 'pi_demo_'.$reference : null,
                        'calendar_event_id' => $status === 'confirmed' ? 'calendar_demo_'.$reference : null,
                        'calendar_sync_status' => $status === 'confirmed' ? 'synced' : 'pending',
                        'meta' => ['demo' => true, 'source' => 'admin_demo_seed'],
                        'created_at' => now()->subDays(max(0, abs($dayOffset) - 1)),
                    ],
                );

                if ($transactionStatus) {
                    FinancialTransaction::updateOrCreate(
                        ['idempotency_key' => 'demo-booking-'.$reference],
                        [
                            'user_id' => $customer->id,
                            'transactable_type' => Booking::class,
                            'transactable_id' => $bookings[$reference]->id,
                            'type' => $payment === 'refunded' ? 'refund' : 'studio_booking',
                            'status' => $transactionStatus,
                            'amount_cents' => $payment === 'refunded' ? -$total : $total,
                            'currency' => 'usd',
                            'stripe_id' => 'pi_demo_'.$reference,
                            'meta' => ['demo' => true],
                            'created_at' => now()->subDays(max(0, abs($dayOffset) - 1)),
                        ],
                    );
                }
            }

            AvailabilityBlock::updateOrCreate(
                ['resource_id' => $studio->id, 'reason' => 'Quarterly equipment maintenance (demo)'],
                [
                    'created_by' => $users['operations']->id,
                    'type' => 'maintenance',
                    'starts_at' => now()->addDays(14)->setTime(8, 0),
                    'ends_at' => now()->addDays(14)->setTime(14, 0),
                ],
            );

            $eventRows = [
                ['DEMO-EVT-2001', 'desert-pulse-showcase', 'Desert Pulse Artist Showcase', -7, 'completed'],
                ['DEMO-EVT-2002', 'soundtunnel-friday-live', 'SoundTunnel Friday Live', 10, 'published'],
                ['DEMO-EVT-2003', 'community-open-decks', 'Community Open Decks', 18, 'submitted'],
                ['DEMO-EVT-2004', 'electronic-producer-night', 'Electronic Producer Night', 25, 'changes_requested'],
            ];
            $events = [];
            foreach ($eventRows as $index => [$reference, $slug, $title, $dayOffset, $status]) {
                $startsAt = now()->addDays($dayOffset)->setTime(19, 0);
                $events[$reference] = Event::updateOrCreate(
                    ['reference' => $reference],
                    [
                        'organizer_id' => $users['organizer']->id,
                        'resource_id' => $soundTunnel->id,
                        'slug' => $slug,
                        'title' => $title,
                        'category' => ['Live music', 'Concert', 'Community', 'Electronic'][$index],
                        'description' => 'Demo event showcasing local artists and the MusiciansWorld creative community.',
                        'location_type' => 'soundtunnel',
                        'venue_name' => 'SoundTunnel',
                        'venue_address' => 'Downtown Phoenix, AZ',
                        'doors_at' => $startsAt->copy()->subHour(),
                        'starts_at' => $startsAt,
                        'ends_at' => $startsAt->copy()->addHours(4),
                        'setup_minutes' => 90,
                        'teardown_minutes' => 60,
                        'capacity' => 250,
                        'visibility' => 'public',
                        'status' => $status,
                        'platform_fee_bps' => 300,
                        'review_notes' => $status === 'changes_requested' ? 'Please upload final artwork and confirm security staffing.' : null,
                        'reviewed_by' => in_array($status, ['completed', 'published', 'changes_requested'], true) ? User::where('email', 'admin@musiciansworld.com')->value('id') : null,
                        'submitted_at' => now()->subDays(4 + $index),
                        'approved_at' => in_array($status, ['completed', 'published'], true) ? now()->subDays(3 + $index) : null,
                        'published_at' => in_array($status, ['completed', 'published'], true) ? now()->subDays(2 + $index) : null,
                    ],
                );
            }

            foreach ($events as $reference => $event) {
                TicketType::updateOrCreate(
                    ['event_id' => $event->id, 'name' => 'General Admission'],
                    [
                        'description' => 'General admission access',
                        'price_cents' => $reference === 'DEMO-EVT-2003' ? 0 : 2500,
                        'quantity' => 180,
                        'sold_count' => $reference === 'DEMO-EVT-2001' ? 42 : ($reference === 'DEMO-EVT-2002' ? 18 : 0),
                        'per_order_limit' => 6,
                        'sales_start_at' => now()->subMonth(),
                        'sales_end_at' => $event->starts_at,
                        'status' => 'active',
                        'is_refundable' => true,
                    ],
                );
            }

            $completedEvent = $events['DEMO-EVT-2001'];
            $ticketType = $completedEvent->ticketTypes()->firstOrFail();
            $order = Order::updateOrCreate(
                ['reference' => 'DEMO-ORD-3001'],
                [
                    'user_id' => $users['customer']->id,
                    'event_id' => $completedEvent->id,
                    'buyer_name' => $users['customer']->name,
                    'buyer_email' => $users['customer']->email,
                    'status' => 'fulfilled',
                    'payment_status' => 'paid',
                    'subtotal_cents' => 5000,
                    'platform_fee_cents' => 150,
                    'processing_fee_cents' => 175,
                    'total_cents' => 5325,
                    'currency' => 'usd',
                    'stripe_checkout_session_id' => 'cs_demo_event_order',
                    'stripe_payment_intent_id' => 'pi_demo_event_order',
                    'created_at' => now()->subDays(9),
                ],
            );
            OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'ticket_type_id' => $ticketType->id],
                ['quantity' => 2, 'unit_price_cents' => 2500, 'total_cents' => 5000],
            );
            foreach ([1, 2] as $number) {
                Ticket::updateOrCreate(
                    ['uuid' => '10000000-0000-4000-8000-'.str_pad((string) $number, 12, '0', STR_PAD_LEFT)],
                    [
                        'order_id' => $order->id,
                        'event_id' => $completedEvent->id,
                        'ticket_type_id' => $ticketType->id,
                        'user_id' => $users['customer']->id,
                        'attendee_name' => $number === 1 ? 'Jordan Customer' : 'Guest Attendee',
                        'attendee_email' => $users['customer']->email,
                        'qr_token_hash' => hash('sha256', 'demo-ticket-'.$number),
                        'status' => $number === 1 ? 'used' : 'valid',
                        'checked_in_at' => $number === 1 ? $completedEvent->starts_at->copy()->subMinutes(20) : null,
                        'checked_in_by' => $number === 1 ? $users['check_in']->id : null,
                    ],
                );
            }

            foreach ([
                ['gross_ticket_revenue', 5000],
                ['mwcn_platform_fee', 150],
                ['organizer_proceeds', 4675],
                ['refund', -2500],
            ] as [$type, $amount]) {
                FinancialTransaction::updateOrCreate(
                    ['idempotency_key' => 'demo-report-'.$type],
                    [
                        'user_id' => $users['customer']->id,
                        'transactable_type' => Order::class,
                        'transactable_id' => $order->id,
                        'type' => $type,
                        'status' => 'succeeded',
                        'amount_cents' => $amount,
                        'currency' => 'usd',
                        'stripe_id' => 'txn_demo_'.$type,
                        'meta' => ['demo' => true, 'event' => $completedEvent->reference],
                        'created_at' => now()->subDays(7),
                    ],
                );
            }

            OrganizerSettlement::updateOrCreate(
                ['event_id' => $completedEvent->id],
                [
                    'status' => 'ready_for_approval',
                    'gross_ticket_revenue_cents' => 5000,
                    'refunds_cents' => 0,
                    'processing_fees_cents' => 175,
                    'platform_fees_cents' => 150,
                    'venue_charges_cents' => 0,
                    'addon_charges_cents' => 0,
                    'adjustments_cents' => 0,
                    'organizer_proceeds_cents' => 4675,
                ],
            );

            foreach ([
                ['contact', 'new', 'Riley Stone', 'riley@example.test', 'I would like a tour of the recording studio.', null],
                ['soundtunnel_request', 'reviewing', 'Nova Events', 'nova@example.test', 'Planning a 150-person album release party.', now()->addMonth()->toDateString()],
                ['dj_lesson', 'contacted', 'Chris Young', 'chris@example.test', 'Interested in private beginner DJ lessons.', null],
                ['membership', 'qualified', 'Jamie Park', 'jamie@example.test', 'Please send details about Founder Membership.', null],
                ['event_inquiry', 'closed', 'Phoenix Arts Group', 'arts@example.test', 'Requesting venue availability for a nonprofit showcase.', now()->addMonths(2)->toDateString()],
            ] as $index => [$type, $status, $name, $email, $message, $eventDate]) {
                FormSubmission::firstOrCreate(
                    ['type' => $type, 'email' => $email],
                    [
                        'user_id' => null,
                        'status' => $status,
                        'name' => $name,
                        'phone' => '602-555-03'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'event_date' => $eventDate,
                        'message' => $message,
                        'data' => ['demo' => true, 'preferred_contact' => 'email'],
                        'created_at' => now()->subHours(($index + 1) * 5),
                    ],
                );
            }

            $program = Program::where('slug', 'dj-fundamentals')->firstOrFail();
            ProgramRegistration::firstOrCreate(
                ['program_id' => $program->id, 'email' => 'student@example.test'],
                [
                    'user_id' => null,
                    'instructor_id' => $users['instructor']->id,
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'name' => 'Drew Student',
                    'phone' => '602-555-0401',
                    'answers' => ['experience' => 'Beginner', 'equipment' => 'Needs equipment'],
                    'notes' => 'Demo registration for the next DJ Fundamentals cohort.',
                ],
            );

            foreach ([
                ['microsoft_calendar', 'create_booking', 'completed', 1, null],
                ['mailtrap', 'send_welcome_email', 'completed', 1, null],
                ['stripe', 'sync_payment', 'failed', 3, 'Demo failure: payment intent could not be retrieved.'],
                ['microsoft_calendar', 'update_event', 'failed', 2, 'Demo failure: calendar token needs reconnection.'],
                ['mailtrap', 'send_event_reminder', 'pending', 0, null],
            ] as [$integration, $action, $status, $attempts, $error]) {
                IntegrationJob::updateOrCreate(
                    ['integration' => $integration, 'action' => $action, 'last_error' => $error],
                    [
                        'subject_type' => Booking::class,
                        'subject_id' => $bookings['DEMO-BKG-1001']->id,
                        'status' => $status,
                        'attempts' => $attempts,
                        'next_attempt_at' => $status === 'failed' ? now()->addMinutes(15) : null,
                        'payload' => ['demo' => true, 'reference' => 'DEMO-BKG-1001'],
                        'created_at' => now()->subHours($attempts + 1),
                    ],
                );
            }

            WebhookEvent::updateOrCreate(
                ['external_id' => 'evt_demo_payment_succeeded'],
                [
                    'provider' => 'stripe',
                    'type' => 'checkout.session.completed',
                    'status' => 'processed',
                    'attempts' => 1,
                    'payload' => ['demo' => true, 'booking_reference' => 'DEMO-BKG-1001'],
                ],
            );

            $adminId = User::where('email', 'admin@musiciansworld.com')->value('id');
            foreach ([
                ['demo.data_seeded', User::class, $users['customer']->id, 'Loaded a complete demonstration data set.'],
                ['booking.payment_status', Booking::class, $bookings['DEMO-BKG-1001']->id, 'Payment confirmed through Stripe demo workflow.'],
                ['event.approve', Event::class, $events['DEMO-EVT-2002']->id, 'Event reviewed and approved for publication.'],
                ['membership_plan.updated', MembershipPlan::class, $plans['founder'], 'Founder benefits reviewed by administration.'],
            ] as [$action, $type, $id, $reason]) {
                AuditLog::firstOrCreate(
                    ['action' => $action, 'auditable_type' => $type, 'auditable_id' => $id, 'reason' => $reason],
                    [
                        'actor_id' => $adminId,
                        'before' => ['demo' => true],
                        'after' => ['status' => 'completed'],
                        'ip_address' => '127.0.0.1',
                        'created_at' => now()->subHours(random_int(1, 24)),
                    ],
                );
            }
        });
    }
}
