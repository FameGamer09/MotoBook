<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'apple'];

    public function redirect(Request $request): RedirectResponse
    {
        $provider = $this->provider($request);
        $registrationFlow = $request->routeIs("register.social.{$provider}");
        $request->session()->put("social_registration_flow.{$provider}", $registrationFlow);

        if (! $this->configured($provider)) {
            return redirect()->route($registrationFlow ? 'register' : 'login')->withErrors([
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
        $registrationFlow = (bool) $request->session()->pull("social_registration_flow.{$provider}", false);

        try {
            $driver = Socialite::driver($provider);
            $identity = $provider === 'apple'
                ? $driver->stateless()->cookieNonce()->user()
                : $driver->user();

            $subject = (string) $identity->getId();
            $email = mb_strtolower(trim((string) $identity->getEmail()));

            if ($subject === '') {
                return $this->failed('The provider did not return a usable account identifier.', $registrationFlow);
            }

            $emailIsVerified = $provider === 'google'
                ? (bool) ($identity->getRaw()['verified_email'] ?? false)
                : false;

            if ($registrationFlow && ($email === '' || ($provider === 'google' && ! $emailIsVerified))) {
                return $this->failed('The provider did not return a verified email address.', true);
            }

            $user = DB::transaction(function () use ($provider, $subject, $email, $identity, $registrationFlow, $emailIsVerified): ?User {
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
                    if (! $registrationFlow || $email === '') {
                        return null;
                    }

                    $user = User::create([
                        'name' => filled($identity->getName()) ? $identity->getName() : Str::before($email, '@'),
                        'email' => $email,
                        'password' => Str::random(64),
                        'role' => 'customer',
                        'status' => 'active',
                        'email_verified_at' => $emailIsVerified ? now() : null,
                    ]);

                    $user->wallet()->create(['balance' => 0]);
                }

                DB::table('social_accounts')->insert([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $subject,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! $user->email_verified_at && $emailIsVerified) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return $user;
            });

            if (! $user) {
                $message = $registrationFlow
                    ? 'This email already belongs to an account that cannot be registered through social sign-up. Please log in or contact support.'
                    : 'No active customer account matches this provider. Sign up with the same email first, then try again.';

                return $this->failed($message, $registrationFlow);
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (Throwable $exception) {
            Log::warning('Social sign-in failed.', [
                'provider' => $provider,
                'exception_type' => $exception::class,
            ]);

            return $this->failed('Sign-in could not be completed. Check the provider configuration and try again.', $registrationFlow);
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

    public function registerRedirect(Request $request): RedirectResponse
    {
        return $this->redirect($request);
    }

    private function failed(string $message, bool $registrationFlow = false): RedirectResponse
    {
        return redirect()->route($registrationFlow ? 'register' : 'login')->withErrors(['email' => $message]);
    }
}
