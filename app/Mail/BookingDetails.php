<?php

namespace App\Mail;

use App\Models\Booking;

class BookingDetails
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public static function rows(Booking $booking): array
    {
        $booking->loadMissing('resource');
        $meta = $booking->meta ?? [];
        $rate = ($meta['rate'] ?? '') === 'first_time' ? 'First-time' : 'Standard';
        if (filled($meta['hourly_cents'] ?? null)) {
            $rate .= ' · $'.number_format(((int) $meta['hourly_cents']) / 100, 0).'/hour';
        }

        $rows = [
            ['label' => 'Reference', 'value' => (string) $booking->reference],
            ['label' => 'Space', 'value' => $booking->resource?->name ?: 'Studio A'],
            ['label' => 'Where', 'value' => LOCATION],
            ['label' => 'From', 'value' => (string) ($meta['from'] ?? $booking->starts_at?->format('m/d/Y H:i'))],
            ['label' => 'To', 'value' => (string) ($meta['to'] ?? $booking->ends_at?->format('m/d/Y H:i'))],
            ['label' => 'Rate', 'value' => $rate],
            ['label' => 'Subtotal', 'value' => a_money($booking->subtotal_cents)],
            ['label' => 'Total', 'value' => a_money($booking->total_cents)],
            ['label' => 'Name', 'value' => (string) $booking->customer_name],
            ['label' => 'Email', 'value' => (string) $booking->customer_email],
            ['label' => 'Phone', 'value' => filled($booking->customer_phone) ? (string) $booking->customer_phone : 'Not provided'],
            ['label' => 'Notes', 'value' => filled($booking->notes) ? (string) $booking->notes : 'None'],
        ];

        if ((int) $booking->discount_cents > 0) {
            array_splice($rows, 7, 0, [['label' => 'Discount', 'value' => a_money($booking->discount_cents)]]);
        }
        if (filled($meta['coupon_code'] ?? null)) {
            $rows[] = ['label' => 'Code', 'value' => (string) $meta['coupon_code']];
        }
        if (filled($meta['pricing_message'] ?? null)) {
            $rows[] = ['label' => 'Pricing', 'value' => (string) $meta['pricing_message']];
        }

        return $rows;
    }
}
