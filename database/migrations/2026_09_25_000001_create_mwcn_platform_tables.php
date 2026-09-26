<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('label');
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->primary(['role_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('membership_plans')) {
            Schema::create('membership_plans', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('billing_interval', 30)->default('month');
                $table->string('stripe_price_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('benefits')) {
            Schema::create('benefits', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('type', 30)->default('boolean');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('benefit_membership_plan')) {
            Schema::create('benefit_membership_plan', function (Blueprint $table) {
                $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('benefit_id')->constrained()->cascadeOnDelete();
                $table->string('value')->nullable();
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_until')->nullable();
                $table->primary(['membership_plan_id', 'benefit_id']);
            });
        }

        if (!Schema::hasTable('customer_memberships')) {
            Schema::create('customer_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('membership_plan_id')->constrained();
                $table->string('status', 30)->default('active')->index();
                $table->string('stripe_customer_id')->nullable()->index();
                $table->string('stripe_subscription_id')->nullable()->unique();
                $table->dateTime('current_period_start')->nullable();
                $table->dateTime('current_period_end')->nullable();
                $table->dateTime('grace_ends_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('type', 40);
                $table->string('timezone')->default('America/Phoenix');
                $table->unsignedInteger('capacity')->nullable();
                $table->string('calendar_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pricing_rules')) {
            Schema::create('pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
                $table->string('service_type', 60)->index();
                $table->string('name');
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 3)->default('usd');
                $table->boolean('is_active')->default(true);
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_until')->nullable();
                $table->json('conditions')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('availability_blocks')) {
            Schema::create('availability_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30)->default('admin_hold');
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('resource_id')->constrained();
                $table->string('service_type', 60)->index();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->dateTime('hold_expires_at')->nullable()->index();
                $table->string('status', 40)->default('pending_payment')->index();
                $table->string('payment_status', 40)->default('pending')->index();
                $table->unsignedInteger('subtotal_cents');
                $table->unsignedInteger('discount_cents')->default(0);
                $table->unsignedInteger('total_cents');
                $table->string('currency', 3)->default('usd');
                $table->string('customer_name');
                $table->string('customer_email');
                $table->string('customer_phone', 40)->nullable();
                $table->text('notes')->nullable();
                $table->string('stripe_checkout_session_id')->nullable()->unique();
                $table->string('stripe_payment_intent_id')->nullable()->index();
                $table->string('calendar_event_id')->nullable();
                $table->string('calendar_sync_status', 30)->default('pending');
                $table->text('calendar_sync_error')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('programs')) {
            Schema::create('programs', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('type', 40)->default('dj_school');
                $table->text('description')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('price_cents')->nullable();
                $table->boolean('payment_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('schedule')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('program_registrations')) {
            Schema::create('program_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('received')->index();
                $table->string('payment_status', 40)->default('none');
                $table->string('name');
                $table->string('email');
                $table->string('phone', 40)->nullable();
                $table->json('answers')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organizer_profiles')) {
            Schema::create('organizer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('display_name');
                $table->string('status', 40)->default('pending');
                $table->boolean('is_verified')->default(false);
                $table->string('stripe_account_id')->nullable()->unique();
                $table->boolean('stripe_charges_enabled')->default(false);
                $table->boolean('stripe_payouts_enabled')->default(false);
                $table->json('requirements')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('organizer_id')->constrained('users');
                $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
                $table->string('slug')->nullable()->unique();
                $table->string('title');
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->string('artwork_path')->nullable();
                $table->string('location_type', 30)->default('soundtunnel');
                $table->string('venue_name')->nullable();
                $table->text('venue_address')->nullable();
                $table->text('virtual_access')->nullable();
                $table->dateTime('doors_at')->nullable();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->unsignedInteger('setup_minutes')->default(0);
                $table->unsignedInteger('teardown_minutes')->default(0);
                $table->unsignedInteger('capacity');
                $table->string('visibility', 20)->default('public');
                $table->string('status', 40)->default('draft')->index();
                $table->unsignedInteger('platform_fee_bps')->default(500);
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('event_services')) {
            Schema::create('event_services', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->unsignedInteger('price_cents');
                $table->string('unit', 30)->default('flat');
                $table->boolean('is_active')->default(true);
                $table->json('requirements')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('event_event_service')) {
            Schema::create('event_event_service', function (Blueprint $table) {
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_service_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedInteger('unit_price_cents');
                $table->string('status', 30)->default('selected');
                $table->primary(['event_id', 'event_service_id']);
            });
        }

        if (!Schema::hasTable('ticket_types')) {
            Schema::create('ticket_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents')->default(0);
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('sold_count')->default(0);
                $table->unsignedInteger('per_order_limit')->default(10);
                $table->dateTime('sales_start_at')->nullable();
                $table->dateTime('sales_end_at')->nullable();
                $table->string('status', 30)->default('active');
                $table->foreignId('required_membership_plan_id')->nullable()->constrained('membership_plans')->nullOnDelete();
                $table->boolean('is_refundable')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->string('buyer_name');
                $table->string('buyer_email');
                $table->string('status', 30)->default('pending')->index();
                $table->string('payment_status', 30)->default('pending')->index();
                $table->unsignedInteger('subtotal_cents');
                $table->unsignedInteger('platform_fee_cents')->default(0);
                $table->unsignedInteger('processing_fee_cents')->default(0);
                $table->unsignedInteger('total_cents');
                $table->string('currency', 3)->default('usd');
                $table->string('stripe_checkout_session_id')->nullable()->unique();
                $table->string('stripe_payment_intent_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_type_id')->constrained();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('unit_price_cents');
                $table->unsignedInteger('total_cents');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_type_id')->constrained();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('attendee_name')->nullable();
                $table->string('attendee_email')->nullable();
                $table->string('qr_token_hash')->unique();
                $table->string('status', 30)->default('valid')->index();
                $table->dateTime('checked_in_at')->nullable();
                $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_transactions')) {
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('transactable');
                $table->string('type', 40)->index();
                $table->string('status', 30)->index();
                $table->integer('amount_cents');
                $table->string('currency', 3)->default('usd');
                $table->string('stripe_id')->nullable()->index();
                $table->string('idempotency_key')->nullable()->unique();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organizer_settlements')) {
            Schema::create('organizer_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('status', 40)->default('not_eligible')->index();
                $table->integer('gross_ticket_revenue_cents')->default(0);
                $table->integer('refunds_cents')->default(0);
                $table->integer('processing_fees_cents')->default(0);
                $table->integer('platform_fees_cents')->default(0);
                $table->integer('venue_charges_cents')->default(0);
                $table->integer('addon_charges_cents')->default(0);
                $table->integer('adjustments_cents')->default(0);
                $table->integer('organizer_proceeds_cents')->default(0);
                $table->string('stripe_transfer_id')->nullable();
                $table->string('stripe_payout_id')->nullable();
                $table->text('hold_reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('agreements')) {
            Schema::create('agreements', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->longText('content');
                $table->string('version');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('agreement_acceptances')) {
            Schema::create('agreement_acceptances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_id')->constrained();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('acceptable');
                $table->string('version');
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('accepted_at');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action')->index();
                $table->nullableMorphs('auditable');
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->string('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('integration_jobs')) {
            Schema::create('integration_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('integration', 40)->index();
                $table->string('action', 80);
                $table->nullableMorphs('subject');
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->dateTime('next_attempt_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30);
                $table->string('external_id')->unique();
                $table->string('type')->index();
                $table->string('status', 30)->default('received')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->json('payload');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('group', 60)->index();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type', 20)->default('string');
                $table->boolean('is_public')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 60)->index();
                $table->string('status', 30)->default('new')->index();
                $table->string('name');
                $table->string('email');
                $table->string('phone', 40)->nullable();
                $table->date('event_date')->nullable();
                $table->text('message')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'form_submissions', 'platform_settings', 'webhook_events', 'integration_jobs', 'audit_logs',
            'agreement_acceptances', 'agreements', 'organizer_settlements',
            'financial_transactions', 'tickets', 'order_items', 'orders', 'ticket_types',
            'event_event_service', 'event_services', 'events', 'organizer_profiles',
            'program_registrations', 'programs', 'bookings', 'availability_blocks',
            'pricing_rules', 'resources', 'customer_memberships', 'benefit_membership_plan',
            'benefits', 'membership_plans', 'role_user', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
