@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2 px-3 py-2 rounded-md bg-status-success-soft text-status-success-ink text-sm font-medium']) }} role="status">
        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
        {{ $status }}
    </div>
@endif
