<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportLegacyBookings extends Command
{
    protected $signature = 'mwcn:import-bookings {file}';
    protected $description = 'Import Phase 1 JSON bookings into the connected booking database';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error('Booking file not found.');
            return self::FAILURE;
        }
        $rows = json_decode(file_get_contents($file), true)['bookings'] ?? [];
        $resource = Resource::where('slug', 'studio-a')->firstOrFail();
        $count = 0;
        foreach ($rows as $row) {
            $starts = CarbonImmutable::parse($row['date'], $resource->timezone)->startOfDay()->addMinutes((int) $row['start'])->utc();
            Booking::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'user_id' => User::where('email', strtolower($row['customer']['email'] ?? ''))->value('id'),
                    'resource_id' => $resource->id,
                    'service_type' => 'studio_booking',
                    'starts_at' => $starts,
                    'ends_at' => $starts->addMinutes((int) $row['durationHours'] * 60),
                    'status' => $row['status'] ?? 'confirmed',
                    'payment_status' => $row['payment']['status'] ?? 'none',
                    'subtotal_cents' => (int) $row['price'] * 100,
                    'total_cents' => (int) $row['price'] * 100,
                    'customer_name' => $row['customer']['name'] ?? '',
                    'customer_email' => $row['customer']['email'] ?? '',
                    'customer_phone' => $row['customer']['phone'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'stripe_checkout_session_id' => $row['payment']['sessionId'] ?? null,
                    'calendar_sync_status' => $row['status'] === 'confirmed' ? 'pending' : 'not_required',
                    'meta' => ['legacy_start_minutes' => (int) $row['start'], 'duration_hours' => (int) $row['durationHours'], 'legacy_payment' => $row['payment'] ?? null],
                    'created_at' => $row['createdAt'] ?? now(),
                ]
            );
            $count++;
        }
        $this->info("Imported {$count} bookings.");
        return self::SUCCESS;
    }
}
