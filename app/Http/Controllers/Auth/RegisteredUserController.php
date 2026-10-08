<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'role' => ['required', 'in:customer,merchant,rider'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $role = $validated['role'];
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role' => $role,
                'status' => $role === 'customer' ? 'active' : 'inactive',
            ]);

            $user->wallet()->create(['balance' => 0]);

            if ($role === 'rider') {
                $user->riderProfile()->create([
                    'vehicle_type' => 'motorcycle',
                    'status' => 'offline',
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return match ($user->role) {
            'merchant' => redirect()->route('merchant.onboarding'),
            'rider' => redirect()->route('rider.onboarding'),
            default => redirect()->route('customer.restaurants'),
        };
    }
}
