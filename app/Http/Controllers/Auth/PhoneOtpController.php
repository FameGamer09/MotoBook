<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PhoneOtpController extends Controller
{
    private const PHONE_COOLDOWN_SECONDS = 60;

    private const HOURLY_PHONE_LIMIT = 5;

    public function __construct(private readonly OtpService $otp) {}

    public function create(): View
    {
        return view('auth.login-phone', [
            'smsConfigured' => $this->otp->isAvailable(),
        ]);
    }

    public function send(SendPhoneOtpRequest $request): RedirectResponse
    {
        $phone = trim($request->validated('phone'));

        if (! $this->otp->isAvailable()) {
            return back()->withErrors([
                'phone' => 'Phone sign-in is not available yet. The administrator must configure the SMS service.',
            ])->withInput();
        }

        if ($response = $this->checkSendLimits($phone)) {
            return $response;
        }

        return $this->deliverCode($phone);
    }

    public function showVerification(): View|RedirectResponse
    {
        $phone = session('otp_phone');

        if (! is_string($phone) || $phone === '') {
            return redirect()->route('login.phone');
        }

        return view('auth.verify-phone', [
            'phone' => $phone,
        ]);
    }

    public function verify(VerifyPhoneOtpRequest $request): RedirectResponse
    {
        $phone = session('otp_phone');

        if (! is_string($phone) || $phone === '') {
            return redirect()->route('login.phone');
        }

        if (! $this->otp->verify($phone, $request->validated('code'))) {
            return back()->withErrors([
                'code' => 'That code is invalid or expired. Request a new code and try again.',
            ]);
        }

        $user = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return redirect()->route('login.phone')->withErrors([
                'phone' => 'We could not verify that account. Check your number and try again.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(): RedirectResponse
    {
        $phone = session('otp_phone');

        if (! is_string($phone) || $phone === '') {
            return redirect()->route('login.phone');
        }

        if (! $this->otp->isAvailable()) {
            return back()->withErrors([
                'code' => 'Phone sign-in is not available yet. The administrator must configure the SMS service.',
            ]);
        }

        if ($response = $this->checkSendLimits($phone, 'code')) {
            return $response;
        }

        return $this->deliverCode($phone, 'code');
    }

    private function checkSendLimits(string $phone, string $errorField = 'phone'): ?RedirectResponse
    {
        $phoneHash = hash('sha256', $phone);
        $cooldownKey = "otp:cooldown:{$phoneHash}";
        $hourlyKey = "otp:hourly:{$phoneHash}";

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return back()->withErrors([
                $errorField => 'Please wait before requesting another verification code.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($hourlyKey, self::HOURLY_PHONE_LIMIT)) {
            return back()->withErrors([
                $errorField => 'Too many verification codes were requested. Please try again later.',
            ]);
        }

        RateLimiter::hit($cooldownKey, self::PHONE_COOLDOWN_SECONDS);
        RateLimiter::hit($hourlyKey, 3600);

        return null;
    }

    private function deliverCode(string $phone, string $errorField = 'phone'): RedirectResponse
    {
        $user = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->first();

        if ($user) {
            try {
                $this->otp->send($phone);
            } catch (SmsDeliveryException $exception) {
                Log::error('Phone OTP delivery failed.', [
                    'exception_type' => $exception::class,
                ]);

                return back()->withErrors([
                    $errorField => 'We could not send the verification code right now. Please try again later.',
                ])->withInput($errorField === 'phone' ? ['phone' => $phone] : []);
            }
        }

        session(['otp_phone' => $phone]);

        return redirect()->route('login.phone.verify')
            ->with('status', 'If this number belongs to an active account, a verification code has been sent.');
    }
}
