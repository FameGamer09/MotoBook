<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OtpService
{
    private const TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SemaphoreSmsGateway $smsGateway) {}

    public function isAvailable(): bool
    {
        return $this->smsGateway->isConfigured();
    }

    public function send(string $phone): void
    {
        $code = (string) random_int(100000, 999999);

        $this->smsGateway->send(
            $phone,
            "Your MotoBook verification code is {$code}. It expires in 5 minutes."
        );

        Cache::put($this->key($phone), hash_hmac('sha256', $code, (string) config('app.key')), self::TTL_SECONDS);
        Cache::put($this->attemptsKey($phone), 0, self::TTL_SECONDS);
    }

    public function verify(string $phone, string $code): bool
    {
        Cache::add($this->attemptsKey($phone), 0, self::TTL_SECONDS);

        if (Cache::get($this->attemptsKey($phone), 0) >= self::MAX_ATTEMPTS) {
            return false;
        }

        $stored = Cache::get($this->key($phone));
        $provided = hash_hmac('sha256', $code, (string) config('app.key'));

        if (! is_string($stored) || ! hash_equals($stored, $provided)) {
            Cache::increment($this->attemptsKey($phone));

            return false;
        }

        Cache::forget($this->key($phone));
        Cache::forget($this->attemptsKey($phone));

        return true;
    }

    private function key(string $phone): string
    {
        return 'otp:'.hash('sha256', $phone);
    }

    private function attemptsKey(string $phone): string
    {
        return $this->key($phone).':attempts';
    }
}
