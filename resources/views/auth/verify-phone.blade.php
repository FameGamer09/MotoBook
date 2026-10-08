<x-guest-layout>
    <div class="mb-6">
        <a href="{{ route('login.phone') }}" class="text-sm underline text-gray-600 hover:text-gray-900">
            {{ __('Change phone number') }}
        </a>
        <h1 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Verify your phone') }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('Enter the six-digit code sent to') }}
            <span class="font-medium text-gray-900">{{ $phone }}</span>.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />
    <x-input-error :messages="$errors->get('code')" class="mb-4" />

    <form method="POST" action="{{ route('login.phone.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Verification code')" />
            <x-text-input
                id="code"
                class="mt-1 block w-full text-center text-lg tracking-[0.5em]"
                type="text"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
            />
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>{{ __('Verify and sign in') }}</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('login.phone.resend') }}" class="mt-5 border-t border-gray-200 pt-4 text-center">
        @csrf
        <button type="submit" class="text-sm underline text-gray-600 hover:text-gray-900">
            {{ __('Send a new code') }}
        </button>
    </form>
</x-guest-layout>
