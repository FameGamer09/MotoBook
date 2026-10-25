<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'apple'];

    public function redirect(Request $request): RedirectResponse
    {
        $provider = $this->provider($request);

        if (! $this->configured($provider)) {
            return redirect()->route('login')->withErrors([
                'email' => ucfirst($provider).' sign-in has not been configured by the administrator yet.',
            ]);
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->scopes(['openid', 'email', 'profile']);
        } elseif ($provider === 'facebook') {
            $driver->scopes(['email']);
        } else {
            // Apple returns its callback as a cross-site POST. The provider's
            // encrypted nonce cookie protects this stateless callback flow.
            $driver->stateless()->cookieNonce();
        }

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $provider = $this->provider($request);

        try {
            $driver = Socialite::driver($provider);
            $identity = $provider === 'apple'
                ? $driver->stateless()->cookieNonce()->user()
                : $driver->user();

            $subject = (string) $identity->getId();
            $email = mb_strtolower(trim((string) $identity->getEmail()));

            if ($subject === '') {
                return $this->failed('The provider did not return a usable account identifier.');
            }

            $user = DB::transaction(function () use ($provider, $subject, $email): ?User {
                $linked = DB::table('social_accounts')
                    ->where('provider', $provider)
                    ->where('provider_user_id', $subject)
                    ->first();

                if ($linked) {
                    return User::query()->whereKey($linked->user_id)
                        ->where('role', 'customer')->where('status', 'active')->first();
                }

                if ($email === '') {
                    return null;
                }

                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('role', 'customer')
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (! $user) {
                    return null;
                }

                DB::table('social_accounts')->insert([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $subject,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! $user->email_verified_at && $email !== '') {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return $user;
            });

            if (! $user) {
                return $this->failed('No active customer account matches this provider. Sign up with the same email first, then try again.');
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (Throwable $exception) {
            Log::warning('Social sign-in failed.', [
                'provider' => $provider,
                'exception_type' => $exception::class,
            ]);

            return $this->failed('Sign-in could not be completed. Check the provider configuration and try again.');
        }
    }

    private function provider(Request $request): string
    {
        $provider = (string) $request->route('provider');

        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return $provider;
    }

    private function configured(string $provider): bool
    {
        $config = config("services.{$provider}", []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
