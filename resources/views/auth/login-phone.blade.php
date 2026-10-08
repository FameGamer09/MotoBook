<x-guest-layout>
    <div class="mb-6">
        <a href="{{ route('login') }}" class="text-sm underline text-gray-600 hover:text-gray-900">
            {{ __('Back to email sign in') }}
        </a>
        <h1 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Sign in with phone') }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('Enter the phone number saved to your account. Include the same country code and format used during registration.') }}
        </p>
    </div>

    @if (! $smsConfigured)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            Phone sign-in needs a configured Semaphore API key. Add it to the server environment before using this option.
        </div>
    @endif

    <x-input-error :messages="$errors->get('phone')" class="mb-4" />

    <form method="POST" action="{{ route('login.phone.send') }}">
        @csrf

        <div>
            <x-input-label for="phone" :value="__('Phone number')" />
            <x-text-input
                id="phone"
                class="mt-1 block w-full"
                type="tel"
                name="phone"
                :value="old('phone')"
                required
                autofocus
                autocomplete="tel"
                placeholder="+639171234567"
            />
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>{{ __('Send verification code') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
