@props(['messages'])

@if ($messages)
    <div {{ $attributes->merge(['class' => 'input-error space-y-0.5']) }} role="alert">
        @foreach ((array) $messages as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif
