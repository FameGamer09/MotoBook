<?php

namespace App\Services;

use App\Exceptions\SmsDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SemaphoreSmsGateway
{
    private const MESSAGES_URL = 'https://api.semaphore.co/api/v4/messages';

    public function isConfigured(): bool
    {
        return filled(config('services.semaphore.api_key'));
    }

    public function send(string $phone, string $message): void
    {
        $apiKey = config('services.semaphore.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new SmsDeliveryException('Semaphore SMS is not configured.');
        }

        $payload = [
            'apikey' => $apiKey,
            'number' => $phone,
            'message' => $message,
        ];
        $senderName = config('services.semaphore.sender_name');

        if (is_string($senderName) && $senderName !== '') {
            $payload['sendername'] = $senderName;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::MESSAGES_URL, $payload)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new SmsDeliveryException('Semaphore could not send the verification code.', previous: $exception);
        }

        if ($response->json('error') !== null) {
            throw new SmsDeliveryException('Semaphore rejected the verification message.');
        }
    }
}
