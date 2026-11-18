<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NextivaSms
{
    public function configured(): bool
    {
        return filled(config('services.nextiva.username'))
            && filled(config('services.nextiva.password'))
            && $this->from() !== null;
    }

    public function toE164(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }
        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        return null;
    }

    public function send(string $to, string $message): void
    {
        $token = $this->token();
        $payload = array_filter([
            'to' => $to,
            'from' => $this->from(),
            'message' => $message,
            'campaignId' => config('services.nextiva.campaign_id') ?: null,
        ], fn (mixed $value): bool => filled($value));

        $response = Http::withToken($token['token'])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post($token['location'].'/users/api/sms', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Nextiva SMS was refused ('.$response->status().').');
        }
    }

    /**
     * @return array{token: string, location: string}
     */
    private function token(): array
    {
        $username = (string) config('services.nextiva.username');

        return Cache::remember('nextiva.token.'.sha1($username), now()->addMinutes(20), function () use ($username): array {
            $response = Http::withBasicAuth($username, (string) config('services.nextiva.password'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(rtrim((string) config('services.nextiva.base_url'), '/').'/provider/token-with-authorities');

            $token = (string) $response->json('token');
            if ($response->failed() || $token === '') {
                throw new RuntimeException('Nextiva login was refused ('.$response->status().').');
            }

            $location = rtrim((string) ($response->json('location') ?: config('services.nextiva.base_url')), '/');

            return ['token' => $token, 'location' => $location];
        });
    }

    private function from(): ?string
    {
        return $this->toE164((string) config('services.nextiva.from'));
    }
}
