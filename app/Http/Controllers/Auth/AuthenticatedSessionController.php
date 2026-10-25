<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SharedPanelAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, SharedPanelAuthentication $panelAuthentication): RedirectResponse
    {
        if ($request->authenticate()) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $authentication = $panelAuthentication->authenticate(
                trim($request->string('email')->toString()),
                $request->string('password')->toString()
            );
        } catch (Throwable $exception) {
            Log::warning('Shared MotoBook sign-in could not reach the management account store.', [
                'exception' => $exception,
            ]);

            $authentication = null;
        }

        if ($authentication !== null) {
            RateLimiter::clear($request->throttleKey());
            $request->session()->regenerate();
            $panelAuthentication->startPanelSession($authentication);

            return redirect()->to($authentication['url']);
        }

        $request->failAuthentication();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
