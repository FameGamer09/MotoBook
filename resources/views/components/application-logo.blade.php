@props(['label' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="w-9 h-9 rounded-md bg-brand-600 text-white grid place-items-center">
        <i data-lucide="bike" class="w-5 h-5"></i>
    </div>
    @if ($label)
        <span class="font-semibold text-white tracking-tight">{{ $label }}</span>
    @endif
</div>
