# MWCN Phase 2 architecture decision

## Source of truth

- MySQL is the operational source of truth.
- `users.email` is the unique customer identity.
- Additive `roles` connect the same account to customer, organizer, instructor, check-in, operations, finance, and super-admin capabilities.
- Memberships, bookings, registrations, events, orders, tickets, transactions, and audit records all reference the same user.
- Microsoft 365 calendars are synchronized operational views, never the booking authority.

## Memberships and benefits

Stripe subscriptions provide billing events. MWCN tables determine active plan status and effective benefits. Plans, prices, benefit values, promoter fee rates, and effective dates are configurable. Cancellation or downgrade changes a membership record and never deletes the customer or history.

## Availability and locking

`resources` is the shared resource registry. Studio A and SoundTunnel bookings, approved SoundTunnel events, setup/teardown windows, and administrative blocks all participate in the same overlap checks. Checkout creates a 15-minute hold. Resource rows and matching reservations are locked in a database transaction before a hold or event approval is accepted.

## Payments and fulfillment

Stripe Checkout is used for card collection. Fulfillment happens only after server-side session retrieval or a signature-verified webhook. Stripe webhook IDs and internal idempotency keys prevent duplicate fulfillment. Every payment, refund, fee, adjustment, transfer, and payout is represented by a financial transaction.

## Stripe Connect recommendation

Use Stripe Connect Express accounts with separate charges and transfers:

1. MWCN is merchant of record for event-ticket checkout.
2. Ticket charges are created on the MWCN platform.
3. Stripe processing fees, refunds, disputes, and negative balance exposure initially fall on the MWCN platform.
4. The configured MWCN platform fee, SoundTunnel charges, add-ons, refunds, processing estimates, and authorized adjustments are distinct ledger categories.
5. Organizer proceeds remain an internal pending liability until event closeout.
6. A transfer to the organizer's connected account is created only after the event is completed, dispute/refund review is clear, Stripe onboarding is complete, and authorized finance staff approve the settlement.
7. Express onboarding keeps bank and identity data in Stripe. MWCN stores only connected-account IDs and readiness flags.
8. Transfer creation uses an idempotency key tied to the settlement ID. Payout/transfer webhooks reconcile final state.

Connect onboarding can be enabled with the `stripe.connect.enabled` platform setting. Real transfers remain disabled until MWCN confirms its Stripe account configuration, fee/refund policy, payout timing, and legal/merchant-of-record position.

## Calendar integration

Microsoft Graph application permissions use client credentials. Dedicated Studio A and SoundTunnel mailbox/calendar IDs are configured through environment variables. Confirmed bookings sync by stored event ID; failures create visible retry records and never reverse a valid payment.

## Ticket security

Each admission has a UUID and an HMAC credential. The QR encodes the check-in URL and credential; only the credential hash is stored. Check-in locks the ticket row and atomically changes `valid` to `checked_in`, so a replay returns `ALREADY_USED`.

## Roles and auditing

Middleware applies least-privilege roles. Critical changes record actor, before/after values, reason, IP, and timestamp. Integration jobs and webhook events expose retries and errors to authorized staff.

## Delivery boundaries

Implemented in Phase 2: connected accounts, membership rules, shared booking inventory, event drafts/review/publication, ticket inventory/orders/QR/check-in, promoter reporting data, ledger/settlement records, admin configuration, Stripe billing/Checkout, Connect onboarding readiness, calendar synchronization, audit history, and visible integration failures.

Deferred configuration-dependent activation: Microsoft Graph production credentials/calendar ownership, live Stripe Connect transfers, finalized package names/prices/benefits, SoundTunnel price/deposit policy, cancellation/refund policy, tax policy, and final staff assignments.
