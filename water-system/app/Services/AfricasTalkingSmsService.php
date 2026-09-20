<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AfricasTalkingSmsService
{
    public function send(string $phone, string $message): void
    {
        $username = trim((string) config('services.africastalking.username'));
        $apiKey = trim((string) config('services.africastalking.api_key'));

        if ($username === '' || $apiKey === '') {
            throw new RuntimeException('Africa\'s Talking SMS credentials are not configured.');
        }

        $environment = strtolower(
            trim((string) config('services.africastalking.environment', 'sandbox'))
        );

        $endpoint = $environment === 'production'
            ? 'https://api.africastalking.com/version1/messaging'
            : 'https://api.sandbox.africastalking.com/version1/messaging';

        $payload = [
            'username' => $username,
            'to' => $this->normalizePhone($phone),
            'message' => $message,
            'enqueue' => 1,
        ];

        $senderId = trim((string) config('services.africastalking.sender_id'));

        if ($senderId !== '') {
            $payload['from'] = $senderId;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withHeaders([
                'apiKey' => $apiKey,
            ])
            ->connectTimeout(5)
            ->timeout(10)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Africa\'s Talking SMS request failed.');
        }

        $recipient = data_get(
            $response->json(),
            'SMSMessageData.Recipients.0'
        );

        $statusCode = (int) data_get($recipient, 'statusCode', 0);

        if (! in_array($statusCode, [100, 101, 102], true)) {
            throw new RuntimeException(
                'Africa\'s Talking did not accept the SMS for delivery.'
            );
        }
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';

        if (str_starts_with($normalized, '0')) {
            $normalized = '+254'.substr($normalized, 1);
        } elseif (str_starts_with($normalized, '254')) {
            $normalized = '+'.$normalized;
        }

        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized)) {
            throw new RuntimeException(
                'The recipient phone number must be a valid international number.'
            );
        }

        return $normalized;
    }
}
