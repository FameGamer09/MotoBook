@props(['label' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="w-9 h-9 rounded-md bg-brand-600 text-white grid place-items-center">
        <img src="{{ asset('images/825310998_1610647237377200_4391978218746131377_n.png') }}" alt="MotoBook" class="h-9 w-9 rounded-md object-cover">
    </div>
    @if ($label)
        <span class="font-semibold text-white tracking-tight">{{ $label }}</span>
    @endif
</div>
