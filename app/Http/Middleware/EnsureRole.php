<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage in routes: ->middleware('role:merchant')
     * or multiple allowed roles: ->middleware('role:merchant,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have access to this section.');
        }

        $isOnboardingRoute = $request->routeIs('merchant.onboarding*', 'rider.onboarding*');

        if ($user->role === 'merchant' && ! $user->store && ! $request->routeIs('merchant.onboarding*')) {
            return redirect()->route('merchant.onboarding');
        }

        if ($user->role === 'rider' && ! $user->riderProfile && ! $request->routeIs('rider.onboarding*')) {
            return redirect()->route('rider.onboarding');
        }

        if ($user->status !== 'active' && ! ($user->status === 'inactive' && $isOnboardingRoute)) {
            auth()->logout();

            return redirect()->route('login')->withErrors('Your account is not active. Contact support.');
        }

        return $next($request);
    }
}
