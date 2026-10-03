@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full px-4 py-2 text-start text-sm font-medium text-brand-700 bg-brand-50 rounded-md transition-colors duration-150'
            : 'block w-full px-4 py-2 text-start text-sm font-medium text-ink-muted hover:text-ink hover:bg-surface-muted rounded-md transition-colors duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
